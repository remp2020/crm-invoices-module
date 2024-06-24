<?php

namespace Crm\InvoicesModule\Events;

use Crm\PaymentsModule\Events\PaymentEventInterface;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class ProformaInvoiceCreatedEvent extends AbstractEvent implements PaymentEventInterface
{
    private $payment;

    public function __construct($payment)
    {
        $this->payment = $payment;
    }

    public function getPayment(): ActiveRow
    {
        return $this->payment;
    }
}
