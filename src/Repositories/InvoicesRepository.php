<?php

namespace Crm\InvoicesModule\Repositories;

use Crm\ApplicationModule\Helpers\UserDateHelper;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Crm\InvoicesModule\Events\NewInvoiceEvent;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\UsersModule\Repositories\AddressesRepository;
use IntlDateFormatter;
use League\Event\Emitter;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Tracy\Debugger;
use Tracy\ILogger;

class InvoicesRepository extends Repository
{
    public const GENERATE_INVOICE_LIMIT_FROM = 'generate_invoice_limit_from';
    public const GENERATE_INVOICE_LIMIT_FROM_DAYS = 'generate_invoice_limit_from_days';
    public const GENERATE_INVOICE_LIMIT_FROM_END_OF_THE_MONTH = 'limit_from_end_of_month';
    public const GENERATE_INVOICE_LIMIT_FROM_PAYMENT = 'limit_from_payment';

    protected $tableName = 'invoices';

    public function __construct(
        Explorer $database,
        private ApplicationConfig $applicationConfig,
        private AddressesRepository $addressesRepository,
        private InvoiceItemsRepository $invoiceItemsRepository,
        AuditLogRepository $auditLogRepository,
        private UserDateHelper $userDateHelper,
        private Emitter $emitter,
        private \Tomaj\Hermes\Emitter $hermesEmitter,
    ) {
        parent::__construct($database);

        $this->auditLogRepository = $auditLogRepository;
        $this->userDateHelper = clone $userDateHelper;
        $this->userDateHelper->setFormat([IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT]);
    }

    final public function add(ActiveRow $user, ActiveRow $payment, ActiveRow $invoiceNumber = null)
    {
        if ($invoiceNumber !== null) {
            // remp/crm#2804
            trigger_error(
                'Parameter `$invoiceNumber` is deprecated. Set invoice number to payment (`$payment->invoice_number_id`) before adding invoice. ' .
                'Support will be removed in next major release.',
                \E_USER_DEPRECATED
            );
        } else {
            $invoiceNumber = $payment->invoice_number;
        }

        $now =  new DateTime();

        $address = $this->addressesRepository->address($user, 'invoice');
        if (!$address) {
            throw new \Exception("Buyer's address is missing. Invoice for payment VS [{$payment->variable_symbol}] cannot be created.");
        }

        // use company name if set; otherwise join first name & last name
        if (isset($address->company_name) && !empty(trim($address->company_name))) {
            $buyerName = trim($address->company_name);
        } else {
            $buyerName = trim(($address->first_name ?? '') . ' ' . ($address->last_name ?? ''));
        }
        // join street & number into address
        if ($address->street) {
            $buyerAddress = trim($address->street . ' ' . $address->number ?? '');
        }

        $data = [
            'buyer_name' => !empty($buyerName) ? $buyerName : null,
            'buyer_address' => $buyerAddress ?? null,
            'buyer_zip' => $address->zip ?? null,
            'buyer_city' => $address->city ?? null,
            'buyer_country_id' => $address->country_id ?? null,
            'buyer_id' => $address->company_id ?? null,
            'buyer_tax_id' => $address->company_tax_id ?? null,
            'buyer_vat_id' => $address->company_vat_id ?? null,

            'supplier_name' => $this->applicationConfig->get('supplier_name'),
            'supplier_address' => $this->applicationConfig->get('supplier_address'),
            'supplier_city' => $this->applicationConfig->get('supplier_city'),
            'supplier_zip' => $this->applicationConfig->get('supplier_zip'),
            'supplier_id' => $this->applicationConfig->get('supplier_id'),
            'supplier_tax_id' => $this->applicationConfig->get('supplier_tax_id'),
            'supplier_vat_id' => $this->applicationConfig->get('supplier_vat_id'),

            'variable_symbol' => $payment->variable_symbol,
            'payment_date' => $payment->paid_at,
            'delivery_date' => $invoiceNumber->delivered_at,
            // set created_date (which is used as issued_date) to same date as invoice number was generated to follow sequence of numbers / invoices
            'created_date' => $this->applicationConfig->get('generate_invoice_number_for_paid_payment') ? $invoiceNumber->delivered_at : $now,
            'updated_date' => $now,
            'invoice_number_id' => $invoiceNumber
        ];

        /** @var ActiveRow $invoice */
        $invoice = $this->insert($data);
        if (!($invoice instanceof ActiveRow)) {
            return $invoice;
        }

        $this->createInvoiceItems($invoice, $payment);

        $this->emitter->emit(new NewInvoiceEvent($invoice));
        $this->hermesEmitter->emit(new HermesMessage('new-invoice', [
            'invoice_id' => $invoice->id
        ]));

        return $invoice;
    }

    final public function updateItems(ActiveRow $invoice): void
    {
        $payment = $invoice->related('payments')->fetch();

        $this->getTransaction()->wrap(function () use ($invoice, $payment) {
            $this->invoiceItemsRepository->getTable()->where([
                'invoice_id' => $invoice->id,
            ])->delete();

            $this->createInvoiceItems($invoice, $payment);
        });
    }

    final public function update(ActiveRow &$invoice, $data): bool
    {
        $data['updated_date'] = new DateTime();
        return parent::update($invoice, $data);
    }

    final public function findBetween(DateTime $fromTime, DateTime $toTime, $field = 'delivery_date')
    {
        return $this->getTable()->where([$field .' >= ?' => $fromTime, $field . ' <= ?' => $toTime]);
    }

    /**
     * Returns true if invoice can be generated.
     */
    final public function isPaymentInvoiceable(ActiveRow $payment, bool $ignoreUserInvoice = false, bool $checkUserAddress = false): bool
    {
        if (!$this->isInvoiceNumberGeneratable($payment)) {
            return false;
        }

        // user setting
        if (!$ignoreUserInvoice && !$payment->user->invoice) {
            return false;
        }

        // admin setting
        if ($payment->user->disable_auto_invoice) {
            return false;
        }

        // fetch returns false if entry doesn't exist (default state) or flag invoiceable is set to true
        $notInvoiceable = $payment->related('payment_meta')
            ->where('key', 'invoiceable')
            ->where('value', 0)
            ->fetch();
        if ($notInvoiceable) {
            return false;
        }

        $invoiceAddress = $this->addressesRepository->address($payment->user, 'invoice');
        if ($checkUserAddress && $invoiceAddress === null) {
            return false;
        }

        if ($invoiceAddress && $payment->payment_country_id && $payment->payment_country_id !== $invoiceAddress->country_id) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if invoice number can be generated for payment.
     */
    final public function isInvoiceNumberGeneratable(ActiveRow $payment): bool
    {
        // check payment status
        if ($payment->status !== PaymentStatusEnum::Paid->value) {
            return false;
        }
        if ($payment->paid_at === null) {
            Debugger::log("There's a paid payment without paid_at date: " . $payment->id, ILogger::WARNING);
            return false;
        }

        if (!$this->paymentInInvoiceablePeriod($payment, new DateTime())) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if payment is still within invoiceable (billing) period and invoice can be generated.
     *
     * Warning: This is not full validation if payment is invoiceable. Use `isPaymentInvoiceable()`.
     */
    final public function paymentInInvoiceablePeriod(ActiveRow $payment, DateTime $now): bool
    {
        $limitFrom = $this->applicationConfig->get(self::GENERATE_INVOICE_LIMIT_FROM);
        $limitDays = $this->applicationConfig->get(self::GENERATE_INVOICE_LIMIT_FROM_DAYS);

        /** @var DateTime $paidAt */
        $paidAt = $payment->paid_at;
        if ($limitFrom === self::GENERATE_INVOICE_LIMIT_FROM_END_OF_THE_MONTH) {
            $maxInvoiceableDate = $paidAt->modifyClone('last day of this month')->modify('+' . $limitDays . 'days 23:59:59');
        } elseif ($limitFrom === self::GENERATE_INVOICE_LIMIT_FROM_PAYMENT) {
            $maxInvoiceableDate = $paidAt->modifyClone('+' . $limitDays . 'days 23:59:59');
        } else {
            $generateInvoiceLimitFromKey = self::GENERATE_INVOICE_LIMIT_FROM;
            throw new \Exception("Invalid application configuration option for config: '{$generateInvoiceLimitFromKey}', value: '{$limitFrom}'");
        }
        return $maxInvoiceableDate >= $now;
    }

    private function createInvoiceItems(ActiveRow $invoice, ActiveRow $payment): void
    {
        // subscription date in invoices depends on fact that each payment has single subscription
        $dateText = "";
        if ($payment->subscription) {
            $dateText = "<br><small>{$this->userDateHelper->process($payment->subscription->start_time)} - {$this->userDateHelper->process($payment->subscription->end_time)}</small>";
        }

        $paymentItems = $payment->related('payment_items');
        $postalFeeVat = null;

        /** @var ActiveRow $item */
        foreach ($paymentItems as $item) {
            $text = $item->name;
            if ($item->type === SubscriptionTypePaymentItem::TYPE) {
                $text .= $dateText;
            }

            $this->invoiceItemsRepository->add(
                $invoice->id,
                $text,
                $item->count,
                $item->amount,
                $item->amount_without_vat,
                $item->vat,
                $this->applicationConfig->get('currency')
            );

            if ($postalFeeVat === null || $item->vat > $postalFeeVat) {
                $postalFeeVat = $item->vat;
            }
        }
    }
}
