<?php

namespace Crm\InvoicesModule;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Helpers\PriceHelper;
use Crm\InvoicesModule\Repository\InvoiceNumbersRepository;
use Crm\InvoicesModule\Repository\InvoicesRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Exception;
use Kdyby\Translation\Translator;
use Latte\Engine;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\UrlScript;
use PdfResponse\PdfResponse;
use Tracy\Debugger;

class InvoiceGenerator
{
    /** @var string */
    private $templateFile;

    /** @var string */
    private $tempDir;

    private $invoicesRepository;

    private $paymentsRepository;

    private $applicationConfig;

    private $invoiceNumbersRepository;

    private $priceHelper;

    private $translator;

    public function __construct(
        InvoicesRepository $invoicesRepository,
        InvoiceNumbersRepository $invoiceNumbersRepository,
        PaymentsRepository $paymentsRepository,
        ApplicationConfig $applicationConfig,
        PriceHelper $priceHelper,
        Translator $translator
    ) {
        $this->invoicesRepository = $invoicesRepository;
        $this->invoiceNumbersRepository = $invoiceNumbersRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->applicationConfig = $applicationConfig;
        $this->priceHelper = $priceHelper;
        $this->translator = $translator;
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

    public function generate($user, $payment)
    {
        if (!$this->invoicesRepository->isPaymentInvoiceable($payment)) {
            throw new Exception("Trying to generate invoice for payment [{$payment->id}] which has `invoiceable` flag set to false.");
        }

        if ($payment->invoice_id == null) {
            $deliveryDate = $this->invoicesRepository->getDeliveryDate($payment);
            $invoiceNumber = $this->invoiceNumbersRepository->getUniqueInvoiceNumber($deliveryDate);
            $invoice = $this->invoicesRepository->add($user, $payment, $invoiceNumber->id);

            $this->paymentsRepository->update($payment, ['invoice_id' => $invoice->id]);
            return $this->renderInvoicePDF($user, $payment);
        }
        return null;
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
            $pdf->outputDestination = PdfResponse::OUTPUT_FILE;
            $pdf->outputName = $filePath;
            $pdf->send(new Request(new UrlScript()), new Response());
            return $pdf;
        }
        return null;
    }

    private function renderInvoice(ActiveRow $payment)
    {
        $invoice = $this->invoicesRepository->find($payment->invoice_id);
        $engine = new Engine();
        $engine->addFilter('price', [$this->priceHelper, 'process']);
        $engine->addFilter('translate', [$this->translator, 'translate']);

        $template = $engine->renderToString(
            $this->getTemplateFile(),
            [
                'invoice' => $invoice,
                'config' => $this->applicationConfig,
            ]
        );

        if (!$template) {
            throw new Exception("Error in rendering invoice template for payment #{$payment->id}", 100);
        }

        $pdf = new PdfResponse($template);
        $pdf->pageFormat = 'A4';
        $pdf->pageMargins = '10,10,10,10,2,6';
        $pdf->documentTitle = 'Invoice';
        $pdf->documentAuthor = $this->applicationConfig->get('supplier_name');
        $pdf->tempDir = $this->getTempDir();
        return $pdf;
    }

    public function renderInvoiceMailAttachment(ActiveRow $payment)
    {
        $attachment = false;
        if ($payment->user->invoice && !$payment->user->disable_auto_invoice) {
            $invoicePdfFile = sys_get_temp_dir() . '/' . $payment->variable_symbol . '.pdf';
            if (!$payment->invoice_id) {
                $this->generate($payment->user, $payment);
                $payment = $this->paymentsRepository->find($payment->id); // refresh the instance to get invoice ID
            }
            $this->renderInvoicePDFToFile(
                $invoicePdfFile,
                $payment->user,
                $payment
            );

            if (file_exists($invoicePdfFile)) {
                $attachment = [
                    'filename' => $payment->variable_symbol . '.pdf',
                    'content' => file_get_contents($invoicePdfFile),
                    'mime_type' => 'application/pdf',
                ];
                unlink($invoicePdfFile);
            } else {
                Debugger::log("Cannot generate invoice for recurrent payment {$payment->variable_symbol}", Debugger::ERROR);
            }
        }
        return $attachment;
    }
}
