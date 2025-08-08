<?php

namespace Crm\InvoicesModule\Models\Vat;

interface VatValidatorServiceResponseInterface
{
    public function isValid(): bool;

    public function getCode(): ?string;

    public function getMessage(): ?string;

    public function getIdentifier(): ?string;

    public function getVatNumber(): ?string;

    public function getName(): ?string;

    public function getAddress(): ?string;

    public function getCountryCode(): ?string;
}
