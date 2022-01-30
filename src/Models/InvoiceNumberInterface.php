<?php

namespace Crm\InvoicesModule\Model;

use Nette\Database\Table\ActiveRow;

interface InvoiceNumberInterface
{
    public function getNextInvoiceNumber(ActiveRow $payment): ActiveRow;
}
