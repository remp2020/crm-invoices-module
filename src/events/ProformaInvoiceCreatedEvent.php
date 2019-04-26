<?php

namespace Crm\InvoicesModule\Events;

use League\Event\AbstractEvent;

class ProformaInvoiceCreatedEvent extends AbstractEvent
{
    private $payment;

    public function __construct($payment)
    {
        $this->payment = $payment;
    }

    public function getPayment()
    {
        return $this->payment;
    }
}
