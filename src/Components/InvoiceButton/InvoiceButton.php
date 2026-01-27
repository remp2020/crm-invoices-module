<?php

namespace Crm\InvoicesModule\Components\InvoiceButton;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\InvoicesModule\Models\BillingDataProviderInterface;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Nette\Utils\DateTime;

/**
 * Download invoice button widget.
 *
 * This components renders simple button to download invoice.
 * Used in user frontend payments listing and admin payments listing.
 *
 * @package Crm\InvoicesModule\Components
 */
class InvoiceButton extends BaseLazyWidget
{
    private string $templateName = 'invoice_button.latte';

    private bool $admin = false;

    public function __construct(
        protected readonly InvoicesRepository $invoicesRepository,
        LazyWidgetManager $lazyWidgetManager,
        protected readonly DataProviderManager $dataProviderManager,
    ) {
        parent::__construct($lazyWidgetManager);
    }

    public function header()
    {
        return 'Faktúra';
    }

    public function identifier()
    {
        return 'userinvoice';
    }

    public function setAdmin()
    {
        $this->admin = true;
    }

    public function render($payment)
    {
        $isInvoiceable = $this->invoicesRepository->isPaymentInvoiceable($payment);
        $hasBillingDataProvider = false;

        if (!$isInvoiceable) {
            /** @var BillingDataProviderInterface[] $providers */
            $providers = $this->dataProviderManager->getProviders('invoices.billing_provider', BillingDataProviderInterface::class);
            foreach ($providers as $provider) {
                if ($provider->isAvailable($payment)) {
                    $isInvoiceable = $provider->isInvoiceable($payment);
                    $hasBillingDataProvider = true;
                    break;
                }
            }
        }

        $this->template->hasBillingDataProvider = $hasBillingDataProvider;
        $this->template->payment = $payment;
        $this->template->paymentInvoicable = $isInvoiceable;
        $this->template->paidButNotInvoiceableAnymore = $payment->paid_at !== null && !$this->invoicesRepository->paymentInInvoiceablePeriod($payment, new DateTime());
        $this->template->admin = $this->admin;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
