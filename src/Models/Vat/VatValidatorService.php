<?php
declare(strict_types=1);

namespace Crm\InvoicesModule\Models\Vat;

use Crm\InvoicesModule\Models\Api\EuVatValidator;
use Tracy\Debugger;
use UnexpectedValueException;

class VatValidatorService implements VatValidatorServiceInterface
{
    /** @var VatValidatorInterface[] */
    private array $validatorsForCountry = [];

    public function __construct(
        private readonly EuVatValidator $euVatValidator,
    ) {
    }

    public function setValidatorForCountry(string $countryCode, VatValidatorInterface $vatValidator): void
    {
        if (isset($this->validatorsForCountry[$countryCode])) {
            throw new UnexpectedValueException("Country validator for country: '{$countryCode}' already registered.");
        }
        $this->validatorsForCountry[$countryCode] = $vatValidator;
    }

    public function validateVatId(string $vatId, string $companyId): VatValidatorServiceResponseInterface
    {
        $countryCode = substr($vatId, 0, 2);
        if (isset($this->validatorsForCountry[$countryCode])) {
            $countryVatValidator = $this->validatorsForCountry[$countryCode];

            try {
                return $countryVatValidator->validateVat($vatId, $companyId);
            } catch (VatValidatorServiceException $vatValidatorServiceException) {
                Debugger::log($vatValidatorServiceException->getMessage(), Debugger::ERROR);
            }
        }

        $response = $this->euVatValidator->validateVat($vatId);
        return new VatValidatorServiceResponse(
            isValid: $response->isValid(),
            vatNumber: $response->getVatNumber(),
            countryCode: $response->getCountryCode(),
            name: $response->getName(),
            address: $response->getAddress(),
        );
    }
}
