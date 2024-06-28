<?php
declare(strict_types=1);

namespace Crm\InvoicesModule\DataProviders;

use Crm\PaymentsModule\DataProviders\OneStopShopCountryResolutionDataProviderInterface;
use Crm\PaymentsModule\Models\OneStopShop\CountryResolution;
use Crm\PaymentsModule\Models\OneStopShop\CountryResolutionType;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShopCountryConflictException;
use Crm\UsersModule\Repositories\AddressesRepository;

final class OneStopShopCountryResolutionDataProvider implements OneStopShopCountryResolutionDataProviderInterface
{
    public function __construct(
        private readonly AddressesRepository $addressesRepository,
    ) {
    }

    public function provide(array $params): ?CountryResolution
    {
        $user = $params['user'] ?? null;
        $selectedCountryCode = $params['selectedCountryCode'] ?? null;
        $paymentAddress = $params['paymentAddress'] ?? null;

        $invoiceAddress = $user ? $this->addressesRepository->address($user, 'invoice') : null;

        if ($invoiceAddress) {
            if ($paymentAddress !== null && $paymentAddress->country?->iso_code !== $invoiceAddress->country->iso_code) {
                throw new OneStopShopCountryConflictException("Conflicting paymentAddress country [{$paymentAddress->country->iso_code}] and invoice country [{$invoiceAddress->country->iso_code}]");
            }

            if ($selectedCountryCode !== null && $selectedCountryCode !== $invoiceAddress->country?->iso_code) {
                throw new OneStopShopCountryConflictException("Conflicting selectedCountryCode [{$selectedCountryCode}] and invoice country [{$invoiceAddress->country->iso_code}]");
            }

            return new CountryResolution($invoiceAddress->country->iso_code, CountryResolutionType::INVOICE_ADDRESS);
        }
        return null;
    }
}
