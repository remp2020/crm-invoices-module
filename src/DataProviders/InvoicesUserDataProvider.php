<?php

namespace Crm\InvoicesModule\DataProviders;

use Crm\ApplicationModule\Models\User\UserDataProviderInterface;
use Crm\InvoicesModule\Models\Generator\InvoiceGenerator;
use Crm\PaymentsModule\Repositories\PaymentsRepository;

class InvoicesUserDataProvider implements UserDataProviderInterface
{
    private $invoiceGenerator;

    private $paymentsRepository;

    public function __construct(
        InvoiceGenerator $invoiceGenerator,
        PaymentsRepository $paymentsRepository,
    ) {
        $this->invoiceGenerator = $invoiceGenerator;
        $this->paymentsRepository = $paymentsRepository;
    }

    public static function identifier(): string
    {
        return 'invoices';
    }

    public function data($userId): ?array
    {
        return null;
    }

    public function download($userId)
    {
        return [];
    }

    public function downloadAttachments($userId)
    {
        $payments = $this->paymentsRepository->userPayments($userId)->where('invoice_id IS NOT NULL');

        $files = [];
        foreach ($payments as $payment) {
            $invoiceFile = tempnam(sys_get_temp_dir(), 'invoice');
            $this->invoiceGenerator->renderInvoicePDFToFile($invoiceFile, $payment->user, $payment);
            $fileName = $payment->invoice->invoice_number->number . '.pdf';
            $files[$fileName] = $invoiceFile;
        }

        return $files;
    }

    public function delete($userId, $protectedData = [])
    {
        return false;
    }

    public function protect($userId): array
    {
        return [];
    }

    public function canBeDeleted($userId): array
    {
        return [true, null];
    }
}
