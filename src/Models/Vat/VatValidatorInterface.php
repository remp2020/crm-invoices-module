<?php

namespace Crm\InvoicesModule\Models\Vat;

interface VatValidatorInterface
{
    public function validateVat(string $vatId, string $companyId): VatValidatorServiceResponse;
}
