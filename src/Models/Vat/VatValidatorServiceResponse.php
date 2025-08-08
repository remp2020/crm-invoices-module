<?php

namespace Crm\InvoicesModule\Models\Vat;

class VatValidatorServiceResponse implements VatValidatorServiceResponseInterface
{
    public function __construct(
        private readonly bool $isValid,
        private readonly ?string $code = null,
        private readonly ?string $message = null,
        private readonly ?string $identifier = null,
        private readonly ?string $vatNumber = null,
        private readonly ?string $countryCode = null,
        private readonly ?string $name = null,
        private readonly ?string $address = null,
    ) {
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }
}
