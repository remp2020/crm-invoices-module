<?php declare(strict_types=1);

namespace Crm\InvoicesModule\DataProviders;

use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\PaymentsModule\DataProviders\ChangePaymentCountryDataProviderInterface;
use Crm\PaymentsModule\Models\OneStopShop\CountryResolution;
use Nette\Database\Table\ActiveRow;

class ChangePaymentCountryDataProvider implements ChangePaymentCountryDataProviderInterface
{
    public function __construct(
        private readonly InvoicesRepository $invoicesRepository,
    ) {
    }

    public function changePaymentCountry(ActiveRow $payment, CountryResolution $countryResolution): void
    {
        $invoice = $payment->invoice;
        if (!$invoice) {
            return;
        }

        $this->invoicesRepository->update($invoice, [
            'buyer_country_id' => $countryResolution->country->id,
        ]);
        $this->invoicesRepository->updateItems($invoice);
    }
}
