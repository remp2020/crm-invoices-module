<?php

namespace Crm\InvoicesModule\Models\Vat;

interface VatValidatorInterface
{
    public function validateVat(string $vatId): VatValidatorServiceResponse;
}
