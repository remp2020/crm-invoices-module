<?php

namespace Crm\InvoicesModule\Events;

use Nette\Database\Table\ActiveRow;

interface InvoiceEventInterface
{
    public function getInvoice(): ActiveRow;
}
