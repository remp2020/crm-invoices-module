<?php

namespace Crm\InvoicesModule\Models\Vat;

use Crm\InvoicesModule\Models\Api\EuVatValidator;
use Crm\InvoicesModule\Models\Api\EuVatValidatorException;
use Crm\PaymentsModule\Models\VatRate\VatMode;
use Crm\PaymentsModule\Repositories\VatRatesRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Nette\Database\Table\ActiveRow;
use Tracy\Debugger;
use Tracy\ILogger;

final class VatModeDetector
{

    public function __construct(
        private readonly AddressesRepository $addressesRepository,
        private readonly VatRatesRepository $vatRatesRepository,
        private readonly CountriesRepository $countriesRepository,
        private EuVatValidator $euVatValidator,
    ) {
    }

    public function setEuVatValidator(EuVatValidator $euVatValidator)
    {
        $this->euVatValidator = $euVatValidator;
    }

    public function userVatMode(ActiveRow $user): VatMode
    {
        $invoiceAddress = $this->addressesRepository->address($user, 'invoice');
        if (!$invoiceAddress || !$invoiceAddress->company_id) {
            return VatMode::B2C;
        }

        // If default country is not an EU country, apply non-europe B2B mode
        // Note: EU country should have a record in `vat_rates` table
        if (!$this->vatRatesRepository->getByCountry($this->countriesRepository->defaultCountry())) {
            return VatMode::B2BNonEurope;
        }

        if (!$invoiceAddress->company_vat_id) {
            return VatMode::B2B;
        }

        // For companies from un-identified country or same as default country, apply standard B2B
        if (!$invoiceAddress->country_id || $invoiceAddress->country_id === $this->countriesRepository->defaultCountry()->id) {
            return VatMode::B2B;
        }

        try {
            $checkVatResponse = $this->euVatValidator->validateVat($invoiceAddress->company_vat_id);

            // Apply reverse-charge only for valid 'company_vat_id'
            if ($checkVatResponse->isValid()) {
                return VatMode::B2BReverseCharge;
            }
            return VatMode::B2B;
        } catch (EuVatValidatorException $euVatValidatorException) {
            // Log exception, but do not crash payment process, treat VAT ID as invalid ID (= B2B)
            Debugger::log($euVatValidatorException->getMessage(), ILogger::EXCEPTION);
            return VatMode::B2B;
        }
    }
}
