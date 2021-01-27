<?php

namespace Crm\InvoicesModule\Sandbox;

use Crm\InvoicesModule\InvoiceGenerator;
use ZipArchive;

class InvoiceZipGenerator
{
    private $sandbox;

    private $invoiceGenerator;

    public function __construct(InvoiceSandbox $sandbox, InvoiceGenerator $invoiceGenerator)
    {
        $this->sandbox = $sandbox;
        $this->invoiceGenerator = $invoiceGenerator;
    }

    public function generate($payments)
    {
        $files = [];
        foreach ($payments as $payment) {
            $invoiceContent = $this->invoiceGenerator->generateInvoiceAsString($payment);

            $fileName = $payment->invoice->invoice_number->number . '.pdf';
            $tmpFile = tmpfile();

            fwrite($tmpFile, $invoiceContent);

            $files[] = [
                'path' => stream_get_meta_data($tmpFile)['uri'],
                'resource' => $tmpFile,
                'name' => $fileName,
            ];
        }

        $zipFile = tempnam(sys_get_temp_dir(), 'invoicesZip_');

        $this->createZip($zipFile, $files);

        foreach ($files as $file) {
            fclose($file['resource']);
        }

        return $zipFile;
    }

    private function createZip($zipFile, $files)
    {
        $zip = new ZipArchive();
        $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $file) {
            $zip->addFile($file['path'], 'invoices/' . $file['name']);
        }
        $zip->close();

        return $zipFile;
    }
}
