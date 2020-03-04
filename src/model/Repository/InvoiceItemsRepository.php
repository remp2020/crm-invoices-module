<?php

namespace Crm\InvoicesModule\Repository;

use Crm\ApplicationModule\Repository;

class InvoiceItemsRepository extends Repository
{
    protected $tableName = 'invoice_items';

    final public function add($invoiceId, $text, $count, $price, $vat, $currency)
    {
        return $this->insert([
            'invoice_id' => $invoiceId,
            'text' => $text,
            'count' => $count,
            'price' => $price,
            'vat' => $vat,
            'currency' => $currency,
        ]);
    }
}
