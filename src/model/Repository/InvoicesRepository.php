<?php

namespace Crm\InvoicesModule\Repository;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Helpers\UserDateHelper;
use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\SubscriptionsModule\PaymentItem\SubscriptionTypePaymentItem;
use Crm\UsersModule\Repository\AddressesRepository;
use IntlDateFormatter;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class InvoicesRepository extends Repository
{
    private $applicationConfig;

    private $addressesRepository;

    private $invoiceItemsRepository;

    private $paymentItemsRepository;

    private $userDateHelper;

    protected $tableName = 'invoices';

    public function __construct(
        Context $database,
        ApplicationConfig $applicationConfig,
        AddressesRepository $addressesRepository,
        InvoiceItemsRepository $invoiceItemsRepository,
        AuditLogRepository $auditLogRepository,
        PaymentItemsRepository $paymentItemsRepository,
        UserDateHelper $userDateHelper
    ) {
        parent::__construct($database);
        $this->applicationConfig = $applicationConfig;
        $this->addressesRepository = $addressesRepository;
        $this->invoiceItemsRepository = $invoiceItemsRepository;
        $this->auditLogRepository = $auditLogRepository;
        $userDateHelper->setFormat([IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT]);
        $this->userDateHelper = $userDateHelper;
        $this->paymentItemsRepository = $paymentItemsRepository;
    }

    public function getDeliveryDate(ActiveRow $payment)
    {
        if ($payment->subscription) {
            return $payment->subscription->start_time > $payment->paid_at ? $payment->paid_at : $payment->subscription->start_time;
        } else {
            return $payment->paid_at;
        }
    }

    public function add(ActiveRow $user, ActiveRow $payment, $invoiceNumber)
    {
        $invoiceCreatedDate = new DateTime();
        $deliveryDate = $this->getDeliveryDate($payment);

        $address = $this->addressesRepository->address($user, 'invoice');
        if (!$address) {
            throw new \Exception("Unable to find [invoice] address for user ID [$user->id].");
        }
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
            'delivery_date' => $deliveryDate,
            'created_date' => $invoiceCreatedDate,
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

    public function findBetween(DateTIme $fromTime, DateTime $toTime, $field = 'delivery_date')
    {
        return $this->getTable()->where([$field .' >= ?' => $fromTime, $field . ' <= ?' => $toTime]);
    }

    /**
     * Returns true if payment is invoiceable.
     *
     * Payments are not-invoiceable only if `payment_meta` flag `invoicable` is set to false.
     */
    public function isPaymentInvoiceable(ActiveRow $payment): bool
    {
        // fetch returns false if entry doesn't exist (default state) or flag invoiceable is set to true
        $notInvoiceable = $payment->related('payment_meta')
            ->where('key', 'invoiceable')
            ->where('value', 0)
            ->fetch();

        return !$notInvoiceable;
    }
}
