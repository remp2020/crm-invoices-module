<?php

namespace Crm\InvoicesModule;

use Contributte\PdfResponse\PdfResponse;
use Contributte\Translation\Translator;
use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Helpers\LocalizedDateHelper;
use Crm\ApplicationModule\Helpers\PriceHelper;
use Crm\ApplicationModule\Helpers\UserDateHelper;
use Latte\Engine;
use Latte\Essential\TranslatorExtension;
use Nette\Database\Table\ActiveRow;
use Tracy\Debugger;

class ReceiptGenerator
{
    /** @var string **/
    private $templateFile;

    /** @var string */
    private $tempDir;

    public function __construct(
        private Translator $translator,
        private PriceHelper $priceHelper,
        private UserDateHelper $userDateHelper,
        private ApplicationConfig $applicationConfig,
        private LocalizedDateHelper $localizedDateHelper,
    ) {
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
            Debugger::log("Unable to find provided receipt template file {$templateFile}. Default template will be used.", Debugger::ERROR);
            return;
        }
        $this->templateFile = $templateFile;
    }

    public function getTemplateFile(): string
    {
        // if no invoice file was provided, load default
        if ($this->templateFile === null) {
            $this->templateFile = __DIR__ . "/templates/receipt/default.latte";
        }
        return $this->templateFile;
    }


    public function generate(ActiveRow $payment)
    {
        $engine = new Engine();
        $engine->addFilter('price', [$this->priceHelper, 'process']);
        $engine->addFilter('userDate', [$this->userDateHelper, 'process']);
        $engine->addFilter('localizedDate', [$this->localizedDateHelper, 'process']);
        $engine->addExtension(new TranslatorExtension($this->translator));

        $template = $engine->renderToString(
            $this->getTemplateFile(),
            [
                'amount' => $payment->amount,
                'project' => $payment->subscription_type->description,
                'config' => $this->applicationConfig,
                'user' => $payment->user,
                'date' => $payment->paid_at,
            ]
        );

        if (!$template) {
            throw new \Exception("Error in rendering receipt template for payment #{$payment->id}", 100);
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
}
