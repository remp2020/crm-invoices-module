<?php

namespace Crm\InvoicesModule\Repository;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Helpers\UserDateHelper;
use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\PaymentItem\SubscriptionTypePaymentItem;
use Crm\UsersModule\Repository\AddressesRepository;
use IntlDateFormatter;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Tracy\Debugger;
use Tracy\ILogger;

class InvoicesRepository extends Repository
{
    public const PAYMENT_INVOICEABLE_PERIOD_DAYS = 15;

    private $applicationConfig;

    private $addressesRepository;

    private $invoiceItemsRepository;

    private $userDateHelper;

    protected $tableName = 'invoices';

    public function __construct(
        Explorer $database,
        ApplicationConfig $applicationConfig,
        AddressesRepository $addressesRepository,
        InvoiceItemsRepository $invoiceItemsRepository,
        AuditLogRepository $auditLogRepository,
        UserDateHelper $userDateHelper
    ) {
        parent::__construct($database);
        $this->applicationConfig = $applicationConfig;
        $this->addressesRepository = $addressesRepository;
        $this->invoiceItemsRepository = $invoiceItemsRepository;
        $this->auditLogRepository = $auditLogRepository;
        $this->userDateHelper = clone $userDateHelper;
        $this->userDateHelper->setFormat([IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT]);
    }

    final public function add(ActiveRow $user, ActiveRow $payment, ActiveRow $invoiceNumber)
    {
        $address = $this->addressesRepository->address($user, 'invoice');

        if (trim($address->company_name) == '' || $address->company_name === null) {
            $buyerName = $address->first_name . ' ' . $address->last_name;
        } else {
            $buyerName = $address->company_name;
        }

        $data = [
            'buyer_name' => $buyerName,
            'buyer_address' => trim("{$address->address} {$address->number}"),
            'buyer_zip' => $address->zip,
            'buyer_city' => $address->city,
            'buyer_country_id' => $address->country_id,
            'buyer_id' => $address->company_id,
            'buyer_tax_id' => $address->company_tax_id,
            'buyer_vat_id' => $address->company_vat_id,
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
            'created_date' => new DateTime(),
            'invoice_number_id' => $invoiceNumber
        ];

        /** @var ActiveRow $invoice */
        $invoice = $this->insert($data);
        if (!$invoice) {
            return $invoice;
        }

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
                $item->vat,
                $this->applicationConfig->get('currency')
            );

            if ($postalFeeVat === null || $item->vat > $postalFeeVat) {
                $postalFeeVat = $item->vat;
            }
        }

        return $invoice;
    }

    final public function findBetween(DateTime $fromTime, DateTime $toTime, $field = 'delivery_date')
    {
        return $this->getTable()->where([$field .' >= ?' => $fromTime, $field . ' <= ?' => $toTime]);
    }

    /**
     * isPaymentInvoiceable returns true if invoice can be generated.
     */
    final public function isPaymentInvoiceable(ActiveRow $payment, bool $ignoreUserInvoice = false): bool
    {
        // user setting
        if (!$ignoreUserInvoice && !$payment->user->invoice) {
            return false;
        }

        // admin setting
        if ($payment->user->disable_auto_invoice) {
            return false;
        }

        // check payment status
        if ($payment->status !== PaymentsRepository::STATUS_PAID) {
            return false;
        }
        if ($payment->paid_at === null) {
            Debugger::log("There's a paid payment without paid_at date: " . $payment->id, ILogger::WARNING);
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

        if (!self::paymentInInvoiceablePeriod($payment, new DateTime())) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if payment is still within invoiceable (billing) period and invoice can be generated.
     *
     * Warning: This is not full validation if payment is invoiceable. Use `isPaymentInvoiceable()`.
     */
    final public static function paymentInInvoiceablePeriod(ActiveRow $payment, DateTime $now): bool
    {
        $maxInvoiceableDate = $payment->paid_at->modifyClone('+' . self::PAYMENT_INVOICEABLE_PERIOD_DAYS . 'days 23:59:59');
        return $maxInvoiceableDate >= $now;
    }
}
