<?php

namespace Crm\InvoicesModule\Model;

use Nette\Database\Table\IRow;

interface InvoiceNumberInterface
{
    public function getNextInvoiceNumber(IRow $payment): IRow;
}
