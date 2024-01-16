<?php

namespace Crm\InvoicesModule\Models\InvoiceNumber;

use Nette\Database\Table\ActiveRow;

interface InvoiceNumberInterface
{
    public function getNextInvoiceNumber(ActiveRow $payment): ActiveRow;
}
