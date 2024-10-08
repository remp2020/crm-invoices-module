<?php

namespace Crm\InvoicesModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;

class InvoiceItemsRepository extends Repository
{
    protected $tableName = 'invoice_items';

    final public function add(int $invoiceId, string $text, int $count, float $price, float $priceWithoutVat, float $vat, string $currency)
    {
        return $this->insert([
            'invoice_id' => $invoiceId,
            'text' => $text,
            'count' => $count,
            'price' => $price,
            'price_without_vat' => $priceWithoutVat,
            'vat' => $vat,
            'currency' => $currency,
        ]);
    }
}
