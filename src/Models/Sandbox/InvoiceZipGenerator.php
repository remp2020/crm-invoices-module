<?php

namespace Crm\InvoicesModule\Sandbox;

use Crm\InvoicesModule\InvoiceGenerator;
use ZipArchive;

class InvoiceZipGenerator
{
    private InvoiceGenerator $invoiceGenerator;

    public function __construct(InvoiceGenerator $invoiceGenerator)
    {
        $this->invoiceGenerator = $invoiceGenerator;
    }

    public function generate($payments): string|false
    {
        $zip = new ZipArchive();
        $zipFile = tempnam(sys_get_temp_dir(), 'invoicesZip_');
        // file cannot exist and be empty when opening with ZipArchive::CREATE
        unlink($zipFile);

        foreach ($payments as $payment) {
            $invoiceContent = $this->invoiceGenerator->generateInvoiceAsString($payment);

            $fileName = $payment->invoice->invoice_number->number . '.pdf';

            $tmpFile = tmpfile();
            fwrite($tmpFile, $invoiceContent);

            $zip->open($zipFile, ZipArchive::CREATE);
            $zip->addFile(stream_get_meta_data($tmpFile)['uri'], 'invoices/' . $fileName);
            $zip->close();

            fclose($tmpFile);
        }

        return $zipFile;
    }
}
