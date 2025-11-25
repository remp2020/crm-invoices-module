<?php

namespace Crm\InvoicesModule\Models\Vat;

interface VatValidatorServiceInterface
{
    public function validateVatId(string $vatId): VatValidatorServiceResponseInterface;
}
