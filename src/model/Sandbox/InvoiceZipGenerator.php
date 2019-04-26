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
        $tmpdir = sys_get_temp_dir() . '/' . time() . rand();
        mkdir($tmpdir);

        $files = [];
        foreach ($payments as $payment) {
            $attachment = $this->invoiceGenerator->renderInvoiceMailAttachment($payment);

            $fileName = $payment->invoice->invoice_number->number . '.pdf';
            $path = $tmpdir . '/' . $fileName;

            file_put_contents($path, $attachment['content']);

            $files[] = [
                'path' => $path,
                'name' => $fileName,
            ];
        }

        $zipFile = tempnam(sys_get_temp_dir(), 'invoice') . '.zip';

        $this->createZip($zipFile, $files);

        // todo vymazat folder potom

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

        foreach ($files as $file) {
            unlink($file['path']);
        }

        return $zipFile;
    }
}
