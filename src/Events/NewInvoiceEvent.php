<?php

namespace Crm\InvoicesModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class NewInvoiceEvent extends AbstractEvent implements InvoiceEventInterface
{
    private ActiveRow $invoice;

    public function __construct($invoice)
    {
        $this->invoice = $invoice;
    }

    public function getInvoice(): ActiveRow
    {
        return $this->invoice;
    }
}
