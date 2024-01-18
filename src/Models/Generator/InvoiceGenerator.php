<?php

namespace Crm\InvoicesModule\Models\Generator;

use Contributte\PdfResponse\PdfResponse;
use Contributte\Translation\Translator;
use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Helpers\PriceHelper;
use Crm\ApplicationModule\RedisClientFactory;
use Crm\ApplicationModule\RedisClientTrait;
use Crm\ApplicationModule\RedisClientTraitException;
use Crm\InvoicesModule\Models\InvoiceNumber\InvoiceNumberInterface;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Latte\Engine;
use Latte\Essential\TranslatorExtension;
use Nette\Database\Table\ActiveRow;
use Tracy\Debugger;
use malkusch\lock\mutex\PredisMutex;

class InvoiceGenerator
{
    use RedisClientTrait;

    /** @var string */
    private $templateFile;

    /** @var string */
    private $tempDir;

    public function __construct(
        private InvoicesRepository $invoicesRepository,
        private PaymentsRepository $paymentsRepository,
        private ApplicationConfig $applicationConfig,
        private PriceHelper $priceHelper,
        private Translator $translator,
        private InvoiceNumberInterface $invoiceNumber,
        RedisClientFactory $redisClientFactory,
        private AddressesRepository $addressesRepository
    ) {
        $this->redisClientFactory = $redisClientFactory;
    }

    public function setTempDir(string $tempDir)
    {
        if (!is_dir($tempDir)) {
            Debugger::log("Providid temp dir {$tempDir} is not directory. System temp directory will be used.", Debugger::ERROR);
            return;
        }
        $this->tempDir = $tempDir;
    }

    public function getTempDir(): string
    {
        // if no temp dir was provided, use system temp dir
        if ($this->tempDir === null) {
            $this->tempDir = sys_get_temp_dir();
        }
        return $this->tempDir;
    }

    public function setTemplateFile(string $templateFile)
    {
        if (!file_exists($templateFile)) {
            Debugger::log("Unable to find provided invoice template file {$templateFile}. Default template will be used.", Debugger::ERROR);
            return;
        }
        $this->templateFile = $templateFile;
    }

    public function getTemplateFile(): string
    {
        // if no invoice file was provided, load default
        if ($this->templateFile === null) {
            $this->templateFile = __DIR__ . "/templates/invoice/default.latte";
        }
        return $this->templateFile;
    }

    /**
     * @throws PaymentNotInvoiceableException
     * @throws InvoiceGenerationException
     * @throws RedisClientTraitException
     */
    public function generate(ActiveRow $user, ActiveRow $payment): ?PdfResponse
    {
        // load before mutex in case config is not in cache (do not want to slow down mutex)
        $generateInvoiceNumberForPaidPayment = filter_var($this->applicationConfig->get('generate_invoice_number_for_paid_payment'), FILTER_VALIDATE_BOOLEAN);

        $mutex = new PredisMutex([$this->redis()], 'invoice_generator_' . $payment->id);
        $mutex->synchronized(function () use ($user, $payment, $generateInvoiceNumberForPaidPayment) {
            // refresh to have current data
            $payment = $this->paymentsRepository->find($payment->id);

            // invoice exists
            if ($payment->invoice_id !== null) {
                return;
            }

            $paymentInvoiceable = $this->invoicesRepository->isPaymentInvoiceable($payment, false, true);

            if ($payment->invoice_number === null) {
                $generateInvoiceNumber = false;

                $invoiceNumberGeneratable = $this->invoicesRepository->isInvoiceNumberGeneratable($payment);

                // generate invoice number if payment is invoiceable
                if ($paymentInvoiceable) {
                    $generateInvoiceNumber = true;
                // or if invoice number generation is enabled for every paid payment
                } elseif ($generateInvoiceNumberForPaidPayment && $invoiceNumberGeneratable) {
                    $generateInvoiceNumber = true;
                }

                if ($generateInvoiceNumber) {
                    $invoiceNumber = $this->invoiceNumber->getNextInvoiceNumber($payment);
                    $this->paymentsRepository->update($payment, [
                        'invoice_number_id' => $invoiceNumber->id,
                    ]);
                }
            }

            // payment is invoiceable => generate invoice
            if ($paymentInvoiceable && $payment->invoice_number) {
                $invoice = $this->invoicesRepository->add($user, $payment);
                $this->paymentsRepository->update($payment, ['invoice_id' => $invoice->id]);
            } else {
                throw new PaymentNotInvoiceableException($payment->id);
            }
        });

        $payment = $this->paymentsRepository->find($payment->id);
        if ($payment->invoice_id === null) {
            return null;
        }

        return $this->renderInvoicePDF($user, $payment);
    }

    public function renderInvoicePDF($user, $payment)
    {
        if ($payment->user->id == $user->id) {
            return $this->renderInvoice($payment);
        }
        return null;
    }

    public function renderInvoicePDFToFile($filePath, $user, $payment)
    {
        if ($payment->user->id == $user->id) {
            $pdf = $this->renderInvoice($payment);
            file_put_contents($filePath, $pdf->toString());
            return $pdf;
        }
        return null;
    }


    /**
     * @param ActiveRow $payment
     *
     * @return PdfResponse
     * @throws InvoiceGenerationException
     */
    private function renderInvoice(ActiveRow $payment)
    {
        $invoice = $this->invoicesRepository->find($payment->invoice_id);
        $engine = new Engine();
        $engine->addFilter('price', [$this->priceHelper, 'process']);
        $engine->addExtension(new TranslatorExtension($this->translator));

        $template = $engine->renderToString(
            $this->getTemplateFile(),
            [
                'invoice' => $invoice,
                'config' => $this->applicationConfig,
            ]
        );

        if (!$template) {
            throw new InvoiceGenerationException("Error in rendering invoice template for payment #{$payment->id}", 100);
        }

        $pdf = new PdfResponse($template);

        $pdf->setPageMargins('10,10,10,10,2,6');
        $pdf->setPageFormat('A4');
        $pdf->setPageOrientation(PdfResponse::ORIENTATION_PORTRAIT);
        $pdf->setDocumentTitle($payment->variable_symbol);
        if ($supplier = $this->applicationConfig->get('supplier_name')) {
            $pdf->setDocumentAuthor($supplier);
        }
        $pdf->mpdfConfig['tempDir'] = $this->getTempDir();

        return $pdf;
    }


    /**
     * Generates invoice PDF file as attachment.
     *
     * If invoice isn't generated for payment and user allowed invoicing, invoice will be generated and linked to payment.
     *
     * @param ActiveRow $payment
     *
     * @return array|bool Returns false if user disabled invoicing.
     * @throws PaymentNotInvoiceableException
     * @throws InvoiceGenerationException
     */
    public function renderInvoiceMailAttachment(ActiveRow $payment)
    {
        if (!$payment->invoice_id) {
            $this->generate($payment->user, $payment);
            $payment = $this->paymentsRepository->find($payment->id); // refresh the instance to get invoice ID
        }

        $attachment = [
            'file' => $payment->variable_symbol . '.pdf',
            'content' => $this->generateInvoiceAsString($payment),
            'mime_type' => 'application/pdf',
        ];

        return $attachment;
    }

    /**
     * Generates invoice PDF file and returns contents as string. Invoice must be already generated and linked to payment.
     */
    public function generateInvoiceAsString(ActiveRow $payment): string
    {
        if ($payment->invoice_id === null) {
            throw new InvoiceGenerationException("No linked invoice for payment VS {$payment->variable_symbol}. Cannot generate PDF attachment.");
        }

        $pdfResponse = $this->renderInvoice($payment);
        return $pdfResponse->toString();
    }
}
