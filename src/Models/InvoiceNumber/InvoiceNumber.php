<?php

namespace Crm\InvoicesModule\Models\InvoiceNumber;

use Crm\InvoicesModule\Repositories\InvoiceNumbersRepository;
use Nette\Database\Table\ActiveRow;

class InvoiceNumber implements InvoiceNumberInterface
{
    public function __construct(
        private InvoiceNumbersRepository $invoiceNumbersRepository,
    ) {
    }

    final public function getNextInvoiceNumber(ActiveRow $payment): ActiveRow
    {
        $deliveredAt = $this->getDeliveryDate($payment);

        /** @var ActiveRow $number */
        $number = $this->invoiceNumbersRepository->insert(['delivered_at' => $deliveredAt]);

        $count = $this->invoiceNumbersRepository->getTable()
            // month condition
            ->where('delivered_at >= ?', $deliveredAt->format('Y-m-01 00:00:00'))
            ->where('delivered_at <= ?', $deliveredAt->format('Y-m-t 23:59:59'))
            // year condition
            ->where('delivered_at >= ?', $deliveredAt->format('Y-01-01 00:00:00'))
            ->where('delivered_at <= ?', $deliveredAt->format('Y-12-31 23:59:59'))
            ->where('id < ?', $number->id)
            ->count('*');

        $invoiceNumber = $deliveredAt->format('y\mm') . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        $this->invoiceNumbersRepository->update($number, ['number' => $invoiceNumber]);
        return $number;
    }

    final public function getDeliveryDate(ActiveRow $payment)
    {
        if ($payment->subscription) {
            return $payment->subscription->start_time > $payment->paid_at ? $payment->paid_at : $payment->subscription->start_time;
        }
        return $payment->paid_at;
    }
}
