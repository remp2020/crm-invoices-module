<?php

namespace Crm\InvoicesModule\Tests\Repositories;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Repositories\ConfigCategoriesRepository;
use Crm\ApplicationModule\Repositories\ConfigsRepository;
use Crm\ApplicationModule\Seeders\ConfigsSeeder as ApplicationConfigsSeeder;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\InvoicesModule\Models\InvoiceNumber\InvoiceNumber;
use Crm\InvoicesModule\Repositories\InvoiceItemsRepository;
use Crm\InvoicesModule\Repositories\InvoiceNumbersRepository;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\InvoicesModule\Seeders\AddressTypesSeeder;
use Crm\InvoicesModule\Seeders\ConfigsSeeder as InvoicesConfigsSeeder;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentItemMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentItemsRepository;
use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\AddressTypesRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class InvoicesRepositoryTest extends DatabaseTestCase
{
    private ApplicationConfig $applicationConfig;
    private ConfigsRepository $configsRepository;
    private InvoiceItemsRepository $invoiceItemsRepository;
    private InvoiceNumbersRepository $invoiceNumbersRepository;
    private InvoicesRepository $invoicesRepository;
    private PaymentsRepository $paymentsRepository;

    private ?ActiveRow $paymentGateway = null;
    private ?ActiveRow $subscriptionType = null;
    private ?ActiveRow $user = null;

    protected function requiredRepositories(): array
    {
        return [
            // invoices
            AddressesRepository::class,
            AddressTypesRepository::class,
            ConfigsRepository::class,
            ConfigCategoriesRepository::class,
            CountriesRepository::class,
            InvoiceItemsRepository::class,
            InvoiceNumbersRepository::class,
            InvoicesRepository::class,
            UsersRepository::class,

            // payments
            PaymentGatewaysRepository::class,
            PaymentsRepository::class,
            PaymentItemsRepository::class,
            PaymentItemMetaRepository::class,
            PaymentMetaRepository::class,

            // subscriptions
            SubscriptionsRepository::class,
            SubscriptionTypesRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            // invoices
            AddressTypesSeeder::class,
            InvoicesConfigsSeeder::class, // supplier details
            ApplicationConfigsSeeder::class, // currency item

            // subscriptions
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->applicationConfig = $this->inject(ApplicationConfig::class);
        $this->configsRepository = $this->getRepository(ConfigsRepository::class);
        $this->invoiceItemsRepository = $this->getRepository(InvoiceItemsRepository::class);
        $this->invoiceNumbersRepository = $this->getRepository(InvoiceNumbersRepository::class);
        $this->invoicesRepository = $this->getRepository(InvoicesRepository::class);
        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
    }

    /* *******************************************************************
     * InvoicesRepository->add() tests
     * ***************************************************************** */

    public function testAddSuccess()
    {
        $user = $this->getUser();
        $payment = $this->addPayment($user, new DateTime(), new DateTime(), $this->getSubscriptionType());
        $address = $this->addUserAddress('invoice');

        // change supplier name and tax it in seeded configs to check it against generated invoice
        $supplierNameConfig = $this->configsRepository->findBy('name', 'supplier_name');
        $supplierNameConfigValue = 'Invoice tester';
        $supplierTaxIdConfig = $this->configsRepository->findBy('name', 'supplier_tax_id');
        $supplierTaxIdConfigValue = '123456789';
        $this->configsRepository->update($supplierNameConfig, ['value' => $supplierNameConfigValue]);
        $this->configsRepository->update($supplierTaxIdConfig, ['value' => $supplierTaxIdConfigValue]);

        /** @var InvoiceNumber $invoiceNumber */
        $invoiceNumber = $this->inject(InvoiceNumber::class);
        $nextInvoiceNumber = $invoiceNumber->getNextInvoiceNumber($payment);
        $this->paymentsRepository->update($payment, ['invoice_number_id' => $nextInvoiceNumber->id]);

        $this->assertEquals(1, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());
        $this->assertEquals(0, $this->invoiceItemsRepository->totalCount());

        // add invoice
        $this->invoicesRepository->add($user, $payment);

        // *******************************************************************
        // test checks start here

        // no change; we generated single invoice number before adding invoice
        $this->assertEquals(1, $this->invoiceNumbersRepository->totalCount());

        // new invoice was created by add()
        $invoices = $this->invoicesRepository->getTable()->fetchAll();
        $this->assertCount(1, $invoices);
        $invoice = reset($invoices);

        // check payment fields
        $this->assertEquals($payment->variable_symbol, $invoice->variable_symbol);
        $this->assertEquals($payment->paid_at, $invoice->payment_date);
        $this->assertEquals($nextInvoiceNumber->id, $invoice->invoice_number_id);
        $this->assertEquals($nextInvoiceNumber->delivered_at, $invoice->delivery_date);

        // check supplier
        // (two values were manually changed by test)
        $this->assertEquals($supplierNameConfigValue, $invoice->supplier_name);
        $this->assertEquals($supplierTaxIdConfigValue, $invoice->supplier_tax_id);
        // (check two values against DB)
        $this->assertEquals($this->applicationConfig->get('supplier_address'), $invoice->supplier_address);
        $this->assertEquals($this->applicationConfig->get('supplier_city'), $invoice->supplier_city);
        // (check rest values against null -> they are seeded as null by default)
        $this->assertNull($invoice->supplier_zip);
        $this->assertNull($invoice->supplier_id);
        $this->assertNull($invoice->supplier_vat_id);

        // check buyer (not a company)
        $this->assertEquals($address->first_name . ' ' . $address->last_name, $invoice->buyer_name);
        $this->assertNotEquals($address->company_name, $invoice->buyer_name);
        $this->assertEquals($address->street . ' ' . $address->number, $invoice->buyer_address);
        $this->assertEquals($address->city, $invoice->buyer_city);
        $this->assertEquals($address->zip, $invoice->buyer_zip);
        $this->assertEquals($address->country_id, $invoice->buyer_country_id);
        // (buyer company details are empty)
        $this->assertEquals('', $invoice->buyer_id);
        $this->assertEquals('', $invoice->buyer_tax_id);
        $this->assertEquals('', $invoice->buyer_vat_id);

        // check invoice items
        $paymentItems = $payment->related('payment_items');
        $this->assertEquals(count($paymentItems), $this->invoiceItemsRepository->totalCount());
        foreach ($paymentItems as $paymentItem) {
            $invoiceItem = $this->invoiceItemsRepository->getTable()->where(['invoice_id' => $invoice->id, 'text LIKE ?' => $paymentItem->name])->fetch();
            $this->assertEquals($paymentItem->amount, $invoiceItem->price);
            $this->assertEquals($paymentItem->vat, $invoiceItem->vat);
        }
    }

    // same as testAddSuccess
    public function testAddSuccessCompany()
    {
        $user = $this->getUser();
        $payment = $this->addPayment($user, new DateTime(), new DateTime(), $this->getSubscriptionType());
        $address = $this->addUserAddress('invoice', $company = true);

        // change supplier name and tax it in seeded configs to check it against generated invoice
        $supplierNameConfig = $this->configsRepository->findBy('name', 'supplier_name');
        $supplierNameConfigValue = 'Invoice tester';
        $supplierTaxIdConfig = $this->configsRepository->findBy('name', 'supplier_tax_id');
        $supplierTaxIdConfigValue = '123456789';
        $this->configsRepository->update($supplierNameConfig, ['value' => $supplierNameConfigValue]);
        $this->configsRepository->update($supplierTaxIdConfig, ['value' => $supplierTaxIdConfigValue]);

        /** @var InvoiceNumber $invoiceNumber */
        $invoiceNumber = $this->inject(InvoiceNumber::class);
        $nextInvoiceNumber = $invoiceNumber->getNextInvoiceNumber($payment);
        $this->paymentsRepository->update($payment, ['invoice_number_id' => $nextInvoiceNumber->id]);

        $this->assertEquals(1, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());
        $this->assertEquals(0, $this->invoiceItemsRepository->totalCount());

        $this->invoicesRepository->add($user, $payment);

        // *******************************************************************
        // test checks start here

        // no change; we generated single invoice number before adding invoice
        $this->assertEquals(1, $this->invoiceNumbersRepository->totalCount());

        // new invoice was created by add()
        $invoices = $this->invoicesRepository->getTable()->fetchAll();
        $this->assertCount(1, $invoices);
        $invoice = reset($invoices);

        // check payment fields
        $this->assertEquals($payment->variable_symbol, $invoice->variable_symbol);
        $this->assertEquals($payment->paid_at, $invoice->payment_date);
        $this->assertEquals($nextInvoiceNumber->id, $invoice->invoice_number_id);
        $this->assertEquals($nextInvoiceNumber->delivered_at, $invoice->delivery_date);

        // check supplier
        // (two values were manually changed by test)
        $this->assertEquals($supplierNameConfigValue, $invoice->supplier_name);
        $this->assertEquals($supplierTaxIdConfigValue, $invoice->supplier_tax_id);
        // (check two values against DB)
        $this->assertEquals($this->applicationConfig->get('supplier_address'), $invoice->supplier_address);
        $this->assertEquals($this->applicationConfig->get('supplier_city'), $invoice->supplier_city);
        // (check rest values against null -> they are seeded as null by default)
        $this->assertNull($invoice->supplier_zip);
        $this->assertNull($invoice->supplier_id);
        $this->assertNull($invoice->supplier_vat_id);

        // check buyer (company)
        $this->assertEquals($address->company_name, $invoice->buyer_name);
        $this->assertNotEquals($address->first_name . ' ' . $address->last_name, $invoice->buyer_name);
        $this->assertEquals($address->street . ' ' . $address->number, $invoice->buyer_address);
        $this->assertEquals($address->city, $invoice->buyer_city);
        $this->assertEquals($address->zip, $invoice->buyer_zip);
        $this->assertEquals($address->country_id, $invoice->buyer_country_id);
        // (buyer company details are not empty for company)
        $this->assertEquals($address->company_id, $invoice->buyer_id);
        $this->assertEquals($address->company_tax_id, $invoice->buyer_tax_id);
        $this->assertEquals($address->company_vat_id, $invoice->buyer_vat_id);

        // check invoice items
        $paymentItems = $payment->related('payment_items');
        $this->assertEquals(count($paymentItems), $this->invoiceItemsRepository->totalCount());
        foreach ($paymentItems as $paymentItem) {
            $invoiceItem = $this->invoiceItemsRepository->getTable()->where(['invoice_id' => $invoice->id, 'text LIKE ?' => $paymentItem->name])->fetch();
            $this->assertEquals($paymentItem->amount, $invoiceItem->price);
            $this->assertEquals($paymentItem->vat, $invoiceItem->vat);
        }
    }

    public function testMissingAddressNoInvoice()
    {
        $user = $this->getUser();
        $payment = $this->addPayment($user, new DateTime(), new DateTime(), $this->getSubscriptionType());

        /** @var InvoiceNumber $invoiceNumber */
        $invoiceNumber = $this->inject(InvoiceNumber::class);
        $nextInvoiceNumber = $invoiceNumber->getNextInvoiceNumber($payment);
        $this->paymentsRepository->update($payment, ['invoice_number_id' => $nextInvoiceNumber->id]);

        $this->assertEquals(1, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());
        $this->assertEquals(0, $this->invoiceItemsRepository->totalCount());

        // catching & testing exception manually so we can continue with tests
        // (expectExceptionObject would stop processing)
        try {
            $this->invoicesRepository->add($user, $payment);
        } catch (\Exception $catchedException) {
            $this->assertEquals($catchedException->getMessage(), "Buyer's address is missing. Invoice for payment VS [{$payment->variable_symbol}] cannot be created.");
        }

        // *******************************************************************
        // test checks start here

        // no change; we generated single invoice number before adding invoice
        $this->assertEquals(1, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());
        $this->assertEquals(0, $this->invoiceItemsRepository->totalCount());
    }

    public function testPaymentIsInInvoiceablePeriodFromPayment()
    {
        $limitFromConfig = $this->configsRepository->loadByName(InvoicesRepository::GENERATE_INVOICE_LIMIT_FROM);
        $this->configsRepository->update($limitFromConfig, [
            'value' => InvoicesRepository::GENERATE_INVOICE_LIMIT_FROM_PAYMENT
        ]);

        $limitFromDaysConfig = $this->configsRepository->loadByName(InvoicesRepository::GENERATE_INVOICE_LIMIT_FROM_DAYS);
        $this->configsRepository->update($limitFromDaysConfig, [
            'value' => 15
        ]);

        $user = $this->getUser();
        $now = new DateTime();
        $in13Days = DateTime::from('+13 days 23:59:59');
        $payment = $this->addPayment($user, $now, $now, $this->getSubscriptionType());

        $isPaymentInvoiceable = $this->invoicesRepository->paymentInInvoiceablePeriod($payment, $in13Days);
        $this->assertTrue($isPaymentInvoiceable);
    }

    public function testPaymentIsNotInInvoiceablePeriodFromPayment()
    {
        $limitFromConfig = $this->configsRepository->loadByName(InvoicesRepository::GENERATE_INVOICE_LIMIT_FROM);
        $this->configsRepository->update($limitFromConfig, [
            'value' => InvoicesRepository::GENERATE_INVOICE_LIMIT_FROM_PAYMENT
        ]);

        $limitFromDaysConfig = $this->configsRepository->loadByName(InvoicesRepository::GENERATE_INVOICE_LIMIT_FROM_DAYS);
        $this->configsRepository->update($limitFromDaysConfig, [
            'value' => 15
        ]);

        $user = $this->getUser();
        $now = new DateTime();
        $in16Days = DateTime::from('+16 days 23:59:59');
        $payment = $this->addPayment($user, $now, $now, $this->getSubscriptionType());

        $isPaymentInvoiceable = $this->invoicesRepository->paymentInInvoiceablePeriod($payment, $in16Days);
        $this->assertFalse($isPaymentInvoiceable);
    }

    public function testPaymentIsInInvoiceablePeriodFromEndOfTheMonth()
    {
        $limitFromConfig = $this->configsRepository->loadByName(InvoicesRepository::GENERATE_INVOICE_LIMIT_FROM);
        $this->configsRepository->update($limitFromConfig, [
            'value' => InvoicesRepository::GENERATE_INVOICE_LIMIT_FROM_END_OF_THE_MONTH
        ]);

        $limitFromDaysConfig = $this->configsRepository->loadByName(InvoicesRepository::GENERATE_INVOICE_LIMIT_FROM_DAYS);
        $this->configsRepository->update($limitFromDaysConfig, [
            'value' => 15
        ]);

        $user = $this->getUser();
        $now = new DateTime();
        $in13DaysFromEndOfTheMonth = $now->modifyClone('last day of this month')->modify('+13 days');
        $payment = $this->addPayment($user, $now, $now, $this->getSubscriptionType());

        $isPaymentInvoiceable = $this->invoicesRepository->paymentInInvoiceablePeriod($payment, $in13DaysFromEndOfTheMonth);
        $this->assertTrue($isPaymentInvoiceable);
    }

    public function testPaymentIsNotInInvoiceablePeriodFromEndOfTheMonth()
    {
        $limitFromConfig = $this->configsRepository->loadByName(InvoicesRepository::GENERATE_INVOICE_LIMIT_FROM);
        $this->configsRepository->update($limitFromConfig, [
            'value' => InvoicesRepository::GENERATE_INVOICE_LIMIT_FROM_END_OF_THE_MONTH
        ]);

        $limitFromDaysConfig = $this->configsRepository->loadByName(InvoicesRepository::GENERATE_INVOICE_LIMIT_FROM_DAYS);
        $this->configsRepository->update($limitFromDaysConfig, [
            'value' => 15
        ]);

        $user = $this->getUser();
        $now = new DateTime();
        $in16DaysFromEndOfTheMonth = $now->modifyClone('last day of this month')->modify('+16 days');
        $payment = $this->addPayment($user, $now, $now, $this->getSubscriptionType());

        $isPaymentInvoiceable = $this->invoicesRepository->paymentInInvoiceablePeriod($payment, $in16DaysFromEndOfTheMonth);
        $this->assertFalse($isPaymentInvoiceable);
    }

    /* *******************************************************************
     * Helper functions
     * ***************************************************************** */

    private function getUser(): ActiveRow
    {
        if ($this->user) {
            return $this->user;
        }

        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);

        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->getRepository(UsersRepository::class);

        $user = $userManager->addNewUser('example@example.com', false, 'unknown', null, false);
        $usersRepository->update($user, [
            'invoice' => true,
            'disable_auto_invoice' => false
        ]);
        return $this->user = $user;
    }

    private function getPaymentGateway(): ActiveRow
    {
        if ($this->paymentGateway) {
            return $this->paymentGateway;
        }

        /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
        $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        return $this->paymentGateway = $paymentGatewaysRepository->add('test', 'test', 10, true, true);
    }

    private function addPayment(
        ActiveRow $user,
        DateTime $startSubscriptionAt,
        DateTime $paidAt = null,
        ActiveRow $subscriptionType = null
    ): ActiveRow {
        if ($subscriptionType) {
            $paymentItemContainer = (new PaymentItemContainer())
                ->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));
        } else {
            $paymentItemContainer = null;
        }

        $payment = $this->paymentsRepository->add(
            $subscriptionType,
            $this->getPaymentGateway(),
            $user,
            $paymentItemContainer,
            null,
            $subscriptionType ? $subscriptionType->price : 1,
            $startSubscriptionAt
        );

        if ($paidAt !== null) {
            $this->paymentsRepository->updateStatus($payment, PaymentStatusEnum::Paid->value);
            $this->paymentsRepository->update($payment, ['paid_at' => $paidAt]);
        }

        return $this->paymentsRepository->find($payment->id);
    }

    private function getSubscriptionType()
    {
        if ($this->subscriptionType) {
            return $this->subscriptionType;
        }

        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $this->subscriptionType = $subscriptionTypeBuilder
            ->createNew()
            ->setName('test_subscription')
            ->setUserLabel('Test subscription')
            ->setActive(true)
            ->setPrice(9.99)
            ->setLength(31)
            ->addSubscriptionTypeItem('Subscription (digital)', 4.99, 20)
            ->addSubscriptionTypeItem('Subscription (print)', 5, 10)
            ->save();

        return $this->subscriptionType;
    }

    private function addUserAddress(string $addressType, bool $company = false): ActiveRow
    {
        /** @var CountriesRepository $countriesRepository */
        $countriesRepository = $this->getRepository(CountriesRepository::class);
        $country = $countriesRepository->add('SK', 'Slovensko', null);

        /** @var AddressesRepository $addressesRepository */
        $addressesRepository = $this->getRepository(AddressesRepository::class);
        return $addressesRepository->add(
            $this->getUser(),
            $addressType,
            'Someone',
            'Somewhat',
            'Very Long Street',
            '42',
            'Neverville',
            '13579',
            $country->id,
            '+99987654321',
            $company ? 'Acme Corporation' : null,
            $company ? 'ID-123456789' : null,
            $company ? 'TAXID-123456789' : null,
            $company ? 'VATID-123456789' : null,
        );
    }
}
