<?php
declare(strict_types=1);

namespace Crm\InvoicesModule\DataProviders;

use Crm\PaymentsModule\DataProviders\OneStopShopCountryResolutionDataProviderInterface;
use Crm\PaymentsModule\Models\OneStopShop\CountryResolution;
use Crm\PaymentsModule\Models\OneStopShop\CountryResolutionTypeEnum;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShopAddressCheckTrait;
use Crm\UsersModule\Repositories\AddressesRepository;

final class OneStopShopCountryResolutionDataProvider implements OneStopShopCountryResolutionDataProviderInterface
{
    use OneStopShopAddressCheckTrait;

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

        if ($invoiceAddress && $invoiceAddress->country) {
            $countryCodesToCheck = array_filter([
                'selectedCountryCode' => $selectedCountryCode,
                'paymentAddressCountryCode' => $paymentAddress?->country?->iso_code,
            ]);

            $this->checkAddresses($countryCodesToCheck, [$invoiceAddress->country->iso_code], 'invoice');

            return new CountryResolution($invoiceAddress->country, CountryResolutionTypeEnum::InvoiceAddress);
        }
        return null;
    }
}
