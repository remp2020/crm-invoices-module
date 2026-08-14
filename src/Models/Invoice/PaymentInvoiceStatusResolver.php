<?php

namespace Crm\InvoicesModule\Models\Invoice;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\InvoicesModule\Models\BillingDataProviderInterface;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\UsersModule\Repositories\AddressesRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

/**
 * Single source of truth for "is a payment's invoice available, and if not, why not".
 */
class PaymentInvoiceStatusResolver
{
    public function __construct(
        private readonly DataProviderManager $dataProviderManager,
        private readonly InvoicesRepository $invoicesRepository,
        private readonly AddressesRepository $addressesRepository,
    ) {
    }

    public function resolve(ActiveRow $payment): PaymentInvoiceStatus
    {
        $billingProvider = $this->resolveBillingProvider($payment);
        $invoiceExists = $payment->invoice !== null;

        if ($billingProvider !== null) {
            // Externally billed payments don't go through our invoiceable/address checks at all.
            return new PaymentInvoiceStatus(
                billingProvider: $billingProvider,
                invoiceExists: $invoiceExists,
                invoiceable: $billingProvider->isInvoiceable($payment),
                periodExpired: false,
                hasInvoiceAddress: false,
                blockedReasonKey: null,
            );
        }

        $invoiceAddress = $this->addressesRepository->address($payment->user, 'invoice');

        return new PaymentInvoiceStatus(
            billingProvider: null,
            invoiceExists: $invoiceExists,
            invoiceable: $this->invoicesRepository->isPaymentInvoiceable($payment),
            periodExpired: $payment->paid_at !== null
                && !$this->invoicesRepository->paymentInInvoiceablePeriod($payment, new DateTime()),
            hasInvoiceAddress: $invoiceAddress !== null,
            blockedReasonKey: $this->resolveBlockedReasonKey($payment, $invoiceAddress),
        );
    }

    private function resolveBillingProvider(ActiveRow $payment): ?BillingDataProviderInterface
    {
        /** @var BillingDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('invoices.billing_provider', BillingDataProviderInterface::class);
        foreach ($providers as $provider) {
            if ($provider->isAvailable($payment)) {
                return $provider;
            }
        }
        return null;
    }

    /**
     * Mirrors the check order of InvoicesRepository::isPaymentInvoiceable() so the reason always matches
     * the actual (dis)ability to generate. That method returns a single bool, not a reason, so this has
     * to be kept in sync by hand if its checks ever change.
     */
    private function resolveBlockedReasonKey(ActiveRow $payment, ?ActiveRow $invoiceAddress): ?string
    {
        if ($payment->status !== PaymentStatusEnum::Paid->value) {
            return 'invoices.component.payment_invoice_detail_widget.reason.not_paid';
        }
        if ($payment->paid_at === null) {
            return 'invoices.component.payment_invoice_detail_widget.reason.missing_paid_at';
        }
        if (!$this->invoicesRepository->paymentInInvoiceablePeriod($payment, new DateTime())) {
            return 'invoices.frontend.invoice_button.not_available_anymore';
        }
        if (!$payment->user->invoice) {
            return 'invoices.component.payment_invoice_detail_widget.reason.user_invoicing_disabled';
        }
        if ($payment->user->disable_auto_invoice) {
            return 'invoices.component.payment_invoice_detail_widget.reason.auto_invoice_disabled';
        }

        $notInvoiceable = $payment->related('payment_meta')
            ->where('key', 'invoiceable')
            ->where('value', 0)
            ->fetch();
        if ($notInvoiceable) {
            return 'invoices.component.payment_invoice_detail_widget.reason.marked_not_invoiceable';
        }

        if ($invoiceAddress === null) {
            return 'invoices.component.payment_invoice_detail_widget.reason.missing_invoice_address';
        }
        if ($payment->payment_country_id && $payment->payment_country_id !== $invoiceAddress->country_id) {
            return 'invoices.component.payment_invoice_detail_widget.reason.country_mismatch';
        }

        return null;
    }
}
