<?php

namespace Crm\InvoicesModule\Models\Generator;

use Contributte\PdfResponse\PdfResponse;
use Contributte\Translation\Translator;
use Crm\ApplicationModule\Helpers\PriceHelper;
use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Models\Redis\RedisClientFactory;
use Crm\ApplicationModule\Models\Redis\RedisClientTrait;
use Crm\ApplicationModule\Models\Redis\RedisClientTraitException;
use Crm\InvoicesModule\Models\InvoiceNumber\InvoiceNumberInterface;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Latte\Engine;
use Latte\Essential\TranslatorExtension;
use Locale;
use Malkusch\Lock\Mutex\RedisMutex;
use Mpdf\MpdfException;
use Nette\Database\Table\ActiveRow;
use Tracy\Debugger;

class InvoiceGenerator
{
    use RedisClientTrait;

    private ?string $templateFile = null;

    private array $templateParams = [];

    private ?string $tempDir = null;

    public function __construct(
        private readonly InvoicesRepository $invoicesRepository,
        private readonly PaymentsRepository $paymentsRepository,
        private readonly ApplicationConfig $applicationConfig,
        private readonly PriceHelper $priceHelper,
        private readonly Translator $translator,
        private readonly InvoiceNumberInterface $invoiceNumber,
        RedisClientFactory $redisClientFactory,
        private readonly AddressesRepository $addressesRepository
    ) {
        $this->redisClientFactory = $redisClientFactory;
    }

    public function setTempDir(string $tempDir): void
    {
        if (!is_dir($tempDir)) {
            Debugger::log("Provided temp dir {$tempDir} is not directory. System temp directory will be used.", Debugger::ERROR);
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

    public function setTemplateFile(string $templateFile): void
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

    public function setTemplateParams(array $params): void
    {
        $this->templateParams = $params;
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

        $mutex = new RedisMutex($this->redis(), 'invoice_generator_' . $payment->id);
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

    /**
     * @param $user
     * @param $payment
     * @return PdfResponse|null
     * @throws InvoiceGenerationException
     */
    public function renderInvoicePDF($user, $payment): ?PdfResponse
    {
        if ($payment->user->id === $user->id) {
            return $this->renderInvoice($payment);
        }
        return null;
    }

    /**
     * @throws InvoiceGenerationException|MpdfException
     */
    public function renderInvoicePDFToFile($filePath, $user, $payment): ?PdfResponse
    {
        if ($payment->user->id === $user->id) {
            $pdf = $this->renderInvoice($payment);
            file_put_contents($filePath, $pdf->toString());
            return $pdf;
        }
        return null;
    }


    /**
     * @throws InvoiceGenerationException
     */
    private function renderInvoice(ActiveRow $payment): PdfResponse
    {
        $locale = Locale::getPrimaryLanguage($this->translator->getLocale());
        $invoice = $this->invoicesRepository->find($payment->invoice_id);
        $engine = new Engine();
        $engine->setLocale($locale);
        $engine->addFilter('price', [$this->priceHelper, 'process']);
        $engine->addExtension(new TranslatorExtension($this->translator));

        $template = $engine->renderToString(
            $this->getTemplateFile(),
            [
                'invoice' => $invoice,
                'config' => $this->applicationConfig,
                'params' => $this->templateParams,
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
     * @return array
     * @throws PaymentNotInvoiceableException
     * @throws InvoiceGenerationException|RedisClientTraitException|MpdfException
     */
    public function renderInvoiceMailAttachment(ActiveRow $payment): array
    {
        if (!$payment->invoice_id) {
            $this->generate($payment->user, $payment);
            $payment = $this->paymentsRepository->find($payment->id); // refresh the instance to get invoice ID
        }

        return [
            'file' => $payment->variable_symbol . '.pdf',
            'content' => $this->generateInvoiceAsString($payment),
            'mime_type' => 'application/pdf',
        ];
    }

    /**
     * Generates invoice PDF file and returns contents as string. Invoice must be already generated and linked to payment.
     *
     * @throws InvoiceGenerationException
     * @throws MpdfException
     */
    public function generateInvoiceAsString(ActiveRow $payment): string
    {
        if ($payment->invoice_id === null) {
            throw new InvoiceGenerationException("No linked invoice for payment VS {$payment->variable_symbol}. Cannot generate PDF attachment.");
        }

        return $this->renderInvoice($payment)->toString();
    }
}
