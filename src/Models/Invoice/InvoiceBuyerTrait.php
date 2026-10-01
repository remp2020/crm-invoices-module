<?php

namespace Crm\InvoicesModule\Models\Invoice;

use Nette\Database\Table\ActiveRow;

trait InvoiceBuyerTrait
{
    protected function buyerName(ActiveRow $address): ?string
    {
        if (isset($address->company_name) && !empty(trim($address->company_name))) {
            return trim($address->company_name);
        }

        $buyerName = trim(($address->first_name ?? '') . ' ' . ($address->last_name ?? ''));
        return !empty($buyerName) ? $buyerName : null;
    }

    protected function buyerAddress(ActiveRow $address): ?string
    {
        if (!$address->street) {
            return null;
        }

        return trim($address->street . ' ' . ($address->number ?? ''));
    }
}
