<?php

namespace Crm\InvoicesModule\Tests\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Repositories\ConfigsRepository;
use Crm\ApplicationModule\Seeders\CountriesSeeder;
use Crm\InvoicesModule\DataProviders\OneStopShopCountryResolutionDataProvider;
use Crm\InvoicesModule\DataProviders\RecurrentPaymentPaymentItemContainerDataProvider;
use Crm\InvoicesModule\DataProviders\VatModeDataProvider;
use Crm\InvoicesModule\Models\Api\EuVatValidator;
use Crm\InvoicesModule\Models\Vat\VatModeDetector;
use Crm\InvoicesModule\Repositories\InvoiceNumbersRepository;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\InvoicesModule\Seeders\AddressTypesSeeder;
use Crm\InvoicesModule\Seeders\ConfigsSeeder;
use Crm\InvoicesModule\Tests\BaseTestCase;
use Crm\PaymentsModule\DataProviders\OneStopShopCountryResolutionDataProviderInterface;
use Crm\PaymentsModule\DataProviders\RecurrentPaymentPaymentItemContainerDataProviderInterface;
use Crm\PaymentsModule\DataProviders\VatModeDataProviderInterface;
use Crm\PaymentsModule\Models\GatewayFactory;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemHelper;
use Crm\PaymentsModule\Models\PaymentProcessor;
use Crm\PaymentsModule\Models\RecurrentPaymentsResolver;
use Crm\PaymentsModule\Models\RecurrentPaymentsResolver\PaymentData;
use Crm\PaymentsModule\Models\VatRate\VatProcessor;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentItemMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentItemsRepository;
use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Repositories\VatRatesRepository;
use Crm\PaymentsModule\Seeders\TestPaymentGatewaysSeeder;
use Crm\PaymentsModule\Tests\Gateways\TestRecurrentGateway;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeItemMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeItemsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Models\Builder\UserBuilder;
use Crm\UsersModule\Repositories\AddressTypesRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\UsersModule\Repositories\LoginAttemptsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use DragonBe\Vies\CheckVatResponse;
use Nette\Database\Table\ActiveRow;

final class RecurrentPaymentPaymentItemContainerDataProviderTest extends BaseTestCase
{
    private PaymentsRepository $paymentsRepository;
    private RecurrentPaymentsRepository $recurrentPaymentsRepository;
    private VatRatesRepository $vatRatesRepository;
    private AddressesRepository $addressesRepository;
    private CountriesRepository $countriesRepository;
    private VatProcessor $vatProcessor;
    private ActiveRow $paymentGateway;
    private RecurrentPaymentsResolver $recurrentPaymentsResolver;
    private SubscriptionTypeItemsRepository $subscriptionTypeItemsRepository;
    private SubscriptionTypesRepository $subscriptionTypesRepository;
    private ActiveRow $franceCountry;

    protected function setUp(): void
    {
        parent::setUp();

        $configsRepository = $this->getRepository(ConfigsRepository::class);
        $configRow = $configsRepository->loadByName('one_stop_shop_enabled');
        $configsRepository->update($configRow, ['value' => 1]);

        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $this->recurrentPaymentsRepository = $this->getRepository(RecurrentPaymentsRepository::class);
        $this->addressesRepository = $this->getRepository(AddressesRepository::class);
        $this->vatRatesRepository = $this->getRepository(VatRatesRepository::class);
        $this->subscriptionTypesRepository = $this->getRepository(SubscriptionTypesRepository::class);
        $this->subscriptionTypeItemsRepository = $this->getRepository(SubscriptionTypeItemsRepository::class);
        $this->countriesRepository = $this->getRepository(CountriesRepository::class);

        $this->vatProcessor = $this->inject(VatProcessor::class);
        $this->recurrentPaymentsResolver = $this->inject(RecurrentPaymentsResolver::class);

        $gatewayFactory = $this->inject(GatewayFactory::class);
        if (!in_array(TestRecurrentGateway::GATEWAY_CODE, $gatewayFactory->getRegisteredCodes(), true)) {
            $gatewayFactory->registerGateway(TestRecurrentGateway::GATEWAY_CODE, TestRecurrentGateway::class);
        }

        $this->paymentGateway = $this->getRepository(PaymentGatewaysRepository::class)
            ->findByCode(TestRecurrentGateway::GATEWAY_CODE);

        $dataProviderManager = $this->inject(DataProviderManager::class);
        $dataProviderManager->registerDataProvider(
            OneStopShopCountryResolutionDataProviderInterface::PATH,
            $this->inject(OneStopShopCountryResolutionDataProvider::class),
            50
        );
        $dataProviderManager->registerDataProvider(
            VatModeDataProviderInterface::PATH,
            $this->inject(VatModeDataProvider::class),
        );
        $dataProviderManager->registerDataProvider(
            RecurrentPaymentPaymentItemContainerDataProviderInterface::PATH,
            $this->inject(RecurrentPaymentPaymentItemContainerDataProvider::class),
            200,
        );

        // Setting countries and VATs
        // in all tests, we use France as foreign country
        $this->countriesRepository->setDefaultCountry('SK');
        $this->franceCountry = $this->countriesRepository->findByIsoCode('FR'); // some EU country
        $this->vatRatesRepository->upsert($this->countriesRepository->defaultCountry(), 20, 10, 10);
        $this->vatRatesRepository->upsert($this->franceCountry, 5, 5, 5);
    }

    protected function requiredSeeders(): array
    {
        return [
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
            CountriesSeeder::class,
            AddressTypesSeeder::class,
            ConfigsSeeder::class,
            TestPaymentGatewaysSeeder::class,
            ContentAccessSeeder::class,
            \Crm\PaymentsModule\Seeders\ConfigsSeeder::class,
        ];
    }

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            LoginAttemptsRepository::class,
            ConfigsRepository::class,

            // To work with subscriptions, we need all these tables
            SubscriptionsRepository::class,
            SubscriptionMetaRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionTypesMetaRepository::class,
            SubscriptionTypeItemsRepository::class,
            SubscriptionTypeItemMetaRepository::class,
            SubscriptionExtensionMethodsRepository::class,
            SubscriptionLengthMethodsRepository::class,

            // Payments + recurrent payments
            PaymentGatewaysRepository::class,
            PaymentItemsRepository::class,
            PaymentItemMetaRepository::class,
            PaymentsRepository::class,
            PaymentMetaRepository::class,

            // Invoices
            InvoicesRepository::class,
            InvoiceNumbersRepository::class,
            AddressesRepository::class,
            AddressTypesRepository::class,
            CountriesRepository::class,

            RecurrentPaymentsRepository::class,
            VatRatesRepository::class,
        ];
    }

    private function assertPaymentItemsEqualTo($payment, $itemsToCompare): void
    {
        $paymentItemsData = [];
        foreach ($payment->related('payment_items') as $pi) {
            $paymentItemsData[$pi->name] = ['name' => $pi->name, 'amount' => $pi->amount, 'vat' => $pi->vat];
        }
        sort($itemsToCompare);
        sort($paymentItemsData);
        foreach (array_keys($itemsToCompare) as $k) {
            $this->assertEqualsCanonicalizing($itemsToCompare[$k], $paymentItemsData[$k]);
        }
        // check sum of payment
        $sumItemsToCompare = 0;
        foreach ($itemsToCompare as $item) {
            $sumItemsToCompare += $item['amount'];
        }
        $this->assertEquals($payment->amount, $sumItemsToCompare);
    }

    public function testRecurrentNonReverseCharge()
    {
        $user = $this->createUser();
        $subscriptionTypeItems = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 20],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 10],
        ];
        $st = $this->createSubscriptionType(['web', 'print'], $subscriptionTypeItems);
        $paymentItemContainer = new PaymentItemContainer();
        $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($st));
        $payment1 = $this->makePayment($user, $st, $paymentItemContainer);
        $this->assertFalse($this->vatProcessor->isReverseChargePayment($payment1));
        $this->assertPaymentItemsEqualTo($payment1, $subscriptionTypeItems);

        $payment2 = $this->makeRecurrentPayment($payment1);
        $this->assertFalse($this->vatProcessor->isReverseChargePayment($payment2));
        $this->assertPaymentItemsEqualTo($payment1, $subscriptionTypeItems);
    }

    public function testRecurrentReverseCharge()
    {
        $user = $this->createUser();
        $this->addCompanyInvoiceAddress($user, $this->franceCountry, 'some_company_id', 'some_vat_id');
        $this->mockEuVatValidator('FR', 'some_vat_id', true);

        $subscriptionTypeItems = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 20],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 10],
        ];
        $subscriptionTypeItemsReverseChargePrices = [
            'web' => ['name' => 'web', 'amount' => 33.33, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 54.55, 'vat' => 0],
        ];
        $st = $this->createSubscriptionType(['web', 'print'], $subscriptionTypeItems);

        // First payment (reverse-charge)
        $paymentItemContainer = new PaymentItemContainer();
        $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($st));
        $payment1 = $this->makePayment($user, $st, $paymentItemContainer, $this->franceCountry);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment1));
        $this->assertPaymentItemsEqualTo($payment1, $subscriptionTypeItemsReverseChargePrices);

        // First recurrent (reverse-charge)
        $payment2 = $this->makeRecurrentPayment($payment1);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment2));
        $this->assertPaymentItemsEqualTo($payment2, $subscriptionTypeItemsReverseChargePrices);

        // Second recurrent (reverse-charge)
        $payment3 = $this->makeRecurrentPayment($payment2);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment3));
        $this->assertPaymentItemsEqualTo($payment3, $subscriptionTypeItemsReverseChargePrices);
    }

    public function testRecurrentReverseChargeWithVatIncreased()
    {
        $user = $this->createUser();
        $this->addCompanyInvoiceAddress($user, $this->franceCountry, 'some_company_id', 'some_vat_id');
        $this->mockEuVatValidator('FR', 'some_vat_id', true);

        $subscriptionTypeItems = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 20],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 10],
        ];
        $subscriptionTypeItemsReverseChargePrices = [
            'web' => ['name' => 'web', 'amount' => 33.33, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 54.55, 'vat' => 0],
        ];
        $subscriptionTypeItemsReverseChargePricesIncreasedVat = [
            'web' => ['name' => 'web', 'amount' => 32.52, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 53.1, 'vat' => 0],
        ];
        $st = $this->createSubscriptionType(['web', 'print'], $subscriptionTypeItems);

        // First payment (reverse-charge)
        $paymentItemContainer = new PaymentItemContainer();
        $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($st));
        $payment1 = $this->makePayment($user, $st, $paymentItemContainer, $this->franceCountry);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment1));
        $this->assertPaymentItemsEqualTo($payment1, $subscriptionTypeItemsReverseChargePrices);

        // Increase VAT of subscription type
        $st = $this->increaseVat($st, 3);

        // First recurrent (reverse-charge)
        $payment2 = $this->makeRecurrentPayment($payment1);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment2));
        $this->assertPaymentItemsEqualTo($payment2, $subscriptionTypeItemsReverseChargePricesIncreasedVat);

        // Second recurrent (reverse-charge)
        $payment3 = $this->makeRecurrentPayment($payment2);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment3));
        $this->assertPaymentItemsEqualTo($payment3, $subscriptionTypeItemsReverseChargePricesIncreasedVat);
    }

    public function testRecurrentReverseChargeWithVatIncreasedAfterRecurrent(): void
    {
        $user = $this->createUser();
        $this->addCompanyInvoiceAddress($user, $this->franceCountry, 'some_company_id', 'some_vat_id');
        $this->mockEuVatValidator('FR', 'some_vat_id', true);

        $subscriptionTypeItems = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 20],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 10],
        ];
        $subscriptionTypeItemsReverseChargePrices = [
            'web' => ['name' => 'web', 'amount' => 33.33, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 54.55, 'vat' => 0],
        ];
        $subscriptionTypeItemsReverseChargePricesIncreasedVat = [
            'web' => ['name' => 'web', 'amount' => 32.52, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 53.1, 'vat' => 0],
        ];
        $st = $this->createSubscriptionType(['web', 'print'], $subscriptionTypeItems);

        // First payment (reverse-charge)
        $paymentItemContainer = new PaymentItemContainer();
        $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($st));
        $payment1 = $this->makePayment($user, $st, $paymentItemContainer, $this->franceCountry);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment1));
        $this->assertPaymentItemsEqualTo($payment1, $subscriptionTypeItemsReverseChargePrices);

        // First recurrent (reverse-charge)
        $payment2 = $this->makeRecurrentPayment($payment1);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment2));
        $this->assertPaymentItemsEqualTo($payment2, $subscriptionTypeItemsReverseChargePrices);

        // Increase VAT of subscription type
        $st = $this->increaseVat($st, 3);

        // Second recurrent (reverse-charge)
        $payment3 = $this->makeRecurrentPayment($payment2);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment3));
        $this->assertPaymentItemsEqualTo($payment3, $subscriptionTypeItemsReverseChargePricesIncreasedVat);
    }

    public function testRecurrentReverseChargeWithPriceIncreased()
    {
        $user = $this->createUser();
        $this->addCompanyInvoiceAddress($user, $this->franceCountry, 'some_company_id', 'some_vat_id');
        $this->mockEuVatValidator('FR', 'some_vat_id', true);

        $subscriptionTypeItems = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 20],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 10],
        ];
        $subscriptionTypeItemsReverseChargePrices = [
            'web' => ['name' => 'web', 'amount' => 33.33, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 54.55, 'vat' => 0],
        ];
        $subscriptionTypeItemsReverseChargeIncreasedPrices = [
            'web' => ['name' => 'web', 'amount' => 33.33, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 63.64, 'vat' => 0],
        ];

        $st = $this->createSubscriptionType(['web', 'print'], $subscriptionTypeItems);

        // First payment (reverse-charge)
        $paymentItemContainer = new PaymentItemContainer();
        $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($st));
        $payment1 = $this->makePayment($user, $st, $paymentItemContainer, $this->franceCountry);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment1));
        $this->assertPaymentItemsEqualTo($payment1, $subscriptionTypeItemsReverseChargePrices);

        // Increase price of subscription type item - print
        $stPrintItem = $st->related('subscription_type_items')->where(['name' => 'print'])->fetch();
        $this->subscriptionTypeItemsRepository->update($stPrintItem, ['amount' => 70], true);

        // First recurrent (reverse-charge)
        $payment2 = $this->makeRecurrentPayment($payment1);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment2));
        $this->assertPaymentItemsEqualTo($payment2, $subscriptionTypeItemsReverseChargeIncreasedPrices);

        // Second recurrent (reverse-charge)
        $payment3 = $this->makeRecurrentPayment($payment2);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment3));
        $this->assertPaymentItemsEqualTo($payment3, $subscriptionTypeItemsReverseChargeIncreasedPrices);
    }

    public function testReverseChargeAfterFirstPaymentB2C()
    {
        $user = $this->createUser(); // user without invoice address

        $subscriptionTypeItems = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 20],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 10],
        ];
        $subscriptionTypeItemsReverseChargePrices = [
            'web' => ['name' => 'web', 'amount' => PaymentItemHelper::getPriceWithoutVAT(40, 20), 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => PaymentItemHelper::getPriceWithoutVAT(60, 10), 'vat' => 0],
        ];
        $st = $this->createSubscriptionType(['web', 'print'], $subscriptionTypeItems);

        // First payment (B2C)
        $paymentItemContainer = new PaymentItemContainer();
        $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($st));
        $payment1 = $this->makePayment($user, $st, $paymentItemContainer);
        $this->assertFalse($this->vatProcessor->isReverseChargePayment($payment1));
        $this->assertPaymentItemsEqualTo($payment1, $subscriptionTypeItems);

        // Now add valid B2B foreign invoice addresss
        $this->addCompanyInvoiceAddress($user, $this->franceCountry, 'some_company_id', 'some_vat_id');
        $this->mockEuVatValidator('FR', 'some_vat_id', true);

        // First recurrent (reverse-charge)
        $payment2 = $this->makeRecurrentPayment($payment1);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment2));
        $this->assertPaymentItemsEqualTo($payment2, $subscriptionTypeItemsReverseChargePrices);

        // Second recurrent (reverse-charge)
        $payment3 = $this->makeRecurrentPayment($payment2);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment3));
        $this->assertPaymentItemsEqualTo($payment3, $subscriptionTypeItemsReverseChargePrices);
    }

    public function testReverseChargeAfterFirstPaymentB2CVatIncreased(): void
    {
        $user = $this->createUser(); // user without invoice address

        $subscriptionTypeItems = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 20],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 10],
        ];
        $subscriptionTypeItemsReverseChargePricesIncreasedVat = [
            'web' => ['name' => 'web', 'amount' => 32.52, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 53.1, 'vat' => 0],
        ];
        $st = $this->createSubscriptionType(['web', 'print'], $subscriptionTypeItems);

        // First payment (B2C)
        $paymentItemContainer = new PaymentItemContainer();
        $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($st));
        $payment1 = $this->makePayment($user, $st, $paymentItemContainer);
        $this->assertFalse($this->vatProcessor->isReverseChargePayment($payment1));
        $this->assertPaymentItemsEqualTo($payment1, $subscriptionTypeItems);

        // Now add valid B2B foreign invoice addresss
        $this->addCompanyInvoiceAddress($user, $this->franceCountry, 'some_company_id', 'some_vat_id');
        $this->mockEuVatValidator('FR', 'some_vat_id', true);

        // Increase VAT of subscription type
        $st = $this->increaseVat($st, 3);

        // First recurrent (reverse-charge)
        $payment2 = $this->makeRecurrentPayment($payment1);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment2));
        $this->assertPaymentItemsEqualTo($payment2, $subscriptionTypeItemsReverseChargePricesIncreasedVat);

        // Second recurrent (reverse-charge)
        $payment3 = $this->makeRecurrentPayment($payment2);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment3));
        $this->assertPaymentItemsEqualTo($payment3, $subscriptionTypeItemsReverseChargePricesIncreasedVat);
    }

    public function testReverseChargeAfterFirstPaymentB2COneStopShop()
    {
        $user = $this->createUser(); // user without invoice address

        $subscriptionTypeItems = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 20],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 10],
        ];
        $subscriptionTypeItemsFranceOneStopShop = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 5],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 5],
        ];
        $subscriptionTypeItemsReverseChargePrices = [
            'web' => ['name' => 'web', 'amount' => 33.33, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 54.55, 'vat' => 0],
        ];
        $st = $this->createSubscriptionType(['web', 'print'], $subscriptionTypeItems);

        // First payment (B2C OneStopShop)
        $paymentItemContainer = new PaymentItemContainer();
        $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($st));
        $payment1 = $this->makePayment($user, $st, $paymentItemContainer, $this->franceCountry);
        $this->assertFalse($this->vatProcessor->isReverseChargePayment($payment1));
        $this->assertPaymentItemsEqualTo($payment1, $subscriptionTypeItemsFranceOneStopShop);

        // Now add valid B2B foreign invoice address
        $this->addCompanyInvoiceAddress($user, $this->franceCountry, 'some_company_id', 'some_vat_id');
        $this->mockEuVatValidator('FR', 'some_vat_id', true);

        // First recurrent (reverse-charge)
        $payment2 = $this->makeRecurrentPayment($payment1);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment2));
        $this->assertPaymentItemsEqualTo($payment2, $subscriptionTypeItemsReverseChargePrices);

        // Second recurrent (reverse-charge)
        $payment3 = $this->makeRecurrentPayment($payment2);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment3));
        $this->assertPaymentItemsEqualTo($payment3, $subscriptionTypeItemsReverseChargePrices);
    }

    public function testReverseChargeAfterFirstPaymentB2COneStopShopWithVatIncreased()
    {
        $user = $this->createUser(); // user without invoice address

        $subscriptionTypeItems = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 20],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 10],
        ];
        $subscriptionTypeItemsFranceOneStopShop = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 5],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 5],
        ];
        $subscriptionTypeItemsReverseChargePricesVatIncreased = [
            'web' => ['name' => 'web', 'amount' => 32.52, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 53.1, 'vat' => 0],
        ];
        $st = $this->createSubscriptionType(['web', 'print'], $subscriptionTypeItems);

        // First payment (B2C OneStopShop)
        $paymentItemContainer = new PaymentItemContainer();
        $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($st));
        $payment1 = $this->makePayment($user, $st, $paymentItemContainer, $this->franceCountry);
        $this->assertFalse($this->vatProcessor->isReverseChargePayment($payment1));
        $this->assertPaymentItemsEqualTo($payment1, $subscriptionTypeItemsFranceOneStopShop);

        // Now add valid B2B foreign invoice address
        $this->addCompanyInvoiceAddress($user, $this->franceCountry, 'some_company_id', 'some_vat_id');
        $this->mockEuVatValidator('FR', 'some_vat_id', true);

        // Increase VAT of subscription type
        $st = $this->increaseVat($st, 3);

        // First recurrent (reverse-charge)
        $payment2 = $this->makeRecurrentPayment($payment1);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment2));
        $this->assertPaymentItemsEqualTo($payment2, $subscriptionTypeItemsReverseChargePricesVatIncreased);

        // Second recurrent (reverse-charge)
        $payment3 = $this->makeRecurrentPayment($payment2);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment3));
        $this->assertPaymentItemsEqualTo($payment3, $subscriptionTypeItemsReverseChargePricesVatIncreased);
    }

    public function testReverseChargeAfterFirstPaymentB2B()
    {
        $user = $this->createUser();
        // Add valid B2B foreign invoice addresss, without VAT ID => B2B payment
        $this->addCompanyInvoiceAddress($user, $this->franceCountry, 'some_company_id');

        $subscriptionTypeItems = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 20],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 10],
        ];
        $subscriptionTypeItemsReverseChargePrices = [
            'web' => ['name' => 'web', 'amount' => 33.33, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 54.55, 'vat' => 0],
        ];
        $st = $this->createSubscriptionType(['web', 'print'], $subscriptionTypeItems);

        // First payment (B2B)
        $paymentItemContainer = new PaymentItemContainer();
        $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($st));
        $payment1 = $this->makePayment($user, $st, $paymentItemContainer, $this->franceCountry);
        $this->assertFalse($this->vatProcessor->isReverseChargePayment($payment1));
        $this->assertPaymentItemsEqualTo($payment1, $subscriptionTypeItems);

        // Now replace invoice address with foreign address with valid VAT ID
        $this->addCompanyInvoiceAddress($user, $this->franceCountry, 'some_company_id', 'some_vat_id');
        $this->mockEuVatValidator('FR', 'some_vat_id', true);

        // First recurrent (reverse-charge)
        $payment2 = $this->makeRecurrentPayment($payment1);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment2));
        $this->assertPaymentItemsEqualTo($payment2, $subscriptionTypeItemsReverseChargePrices);

        // Second recurrent (reverse-charge)
        $payment3 = $this->makeRecurrentPayment($payment2);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment3));
        $this->assertPaymentItemsEqualTo($payment3, $subscriptionTypeItemsReverseChargePrices);
    }

    public function testReverseChargeAfterFirstPaymentB2BWithVatIncreased()
    {
        $user = $this->createUser();
        // Add valid B2B foreign invoice addresss, without VAT ID => B2B payment
        $this->addCompanyInvoiceAddress($user, $this->franceCountry, 'some_company_id');

        $subscriptionTypeItems = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 20],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 10],
        ];
        $subscriptionTypeItemsReverseChargePricesVatIncreased = [
            'web' => ['name' => 'web', 'amount' => 32.79, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 53.57, 'vat' => 0],
        ];
        $st = $this->createSubscriptionType(['web', 'print'], $subscriptionTypeItems);

        // First payment (B2B)
        $paymentItemContainer = new PaymentItemContainer();
        $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($st));
        $payment1 = $this->makePayment($user, $st, $paymentItemContainer, $this->franceCountry);
        $this->assertFalse($this->vatProcessor->isReverseChargePayment($payment1));
        $this->assertPaymentItemsEqualTo($payment1, $subscriptionTypeItems);

        // Now replace invoice address with foreign address with valid VAT ID
        $this->addCompanyInvoiceAddress($user, $this->franceCountry, 'some_company_id', 'some_vat_id');
        $this->mockEuVatValidator('FR', 'some_vat_id', true);

        $st = $this->increaseVat($st, 2);

        // First recurrent (reverse-charge)
        $payment2 = $this->makeRecurrentPayment($payment1);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment2));
        $this->assertPaymentItemsEqualTo($payment2, $subscriptionTypeItemsReverseChargePricesVatIncreased);

        // Second recurrent (reverse-charge)
        $payment3 = $this->makeRecurrentPayment($payment2);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment3));
        $this->assertPaymentItemsEqualTo($payment3, $subscriptionTypeItemsReverseChargePricesVatIncreased);
    }

    /**
     * Scenario:
     * - we manually applied reverse-charge on a payment in the past, VAT was subtracted from payment amount
     * - before first recurrent payment a valid B2B invoice address is assigned to the user
     * - recurrent payment is done in proper reverse-charge mode
     */
    public function testFirstPaymentUnmarkedReverseCharge()
    {
        $user = $this->createUser();

        $subscriptionTypeItems = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 20],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 10],
        ];
        $subscriptionTypeItemsReverseChargePrices = [
            'web' => ['name' => 'web', 'amount' => 33.33, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 54.55, 'vat' => 0],
        ];
        $st = $this->createSubscriptionType(['web', 'print'], $subscriptionTypeItems);

        // First payment - fake reverse-charge
        // OSS is not applied since there is no payment_country assigned to the payment
        $paymentItemContainer = new PaymentItemContainer();
        $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($st));
        // remove VAT
        foreach ($paymentItemContainer->items() as $paymentItem) {
            $paymentItem->forcePrice($paymentItem->unitPriceWithoutVAT());
            $paymentItem->forceVat(0);
        }

        $payment1 = $this->makePayment($user, $st, $paymentItemContainer);
        $this->assertFalse($this->vatProcessor->isReverseChargePayment($payment1));
        $this->assertPaymentItemsEqualTo($payment1, $subscriptionTypeItemsReverseChargePrices);

        // Now add invoice address with foreign address with valid VAT ID
        $this->addCompanyInvoiceAddress($user, $this->franceCountry, 'some_company_id', 'some_vat_id');
        $this->mockEuVatValidator('FR', 'some_vat_id', true);

        // First recurrent (true reverse-charge)
        $payment2 = $this->makeRecurrentPayment($payment1);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment2));
        $this->assertPaymentItemsEqualTo($payment2, $subscriptionTypeItemsReverseChargePrices);

        // Second recurrent (true reverse-charge)
        $payment3 = $this->makeRecurrentPayment($payment2);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment3));
        $this->assertPaymentItemsEqualTo($payment3, $subscriptionTypeItemsReverseChargePrices);
    }

    public function testFirstPaymentUnmarkedReverseChargeWithVatIncreased()
    {
        $user = $this->createUser();

        $subscriptionTypeItems = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 20],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 10],
        ];
        $subscriptionTypeItemsReverseChargePrices = [
            'web' => ['name' => 'web', 'amount' => 33.33, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 54.55, 'vat' => 0],
        ];
        $subscriptionTypeItemsReverseChargePricesVatIncreased = [
            'web' => ['name' => 'web', 'amount' => 32, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 52.17, 'vat' => 0],
        ];

        $st = $this->createSubscriptionType(['web', 'print'], $subscriptionTypeItems);

        // First payment - fake reverse-charge
        // OSS is not applied since there is no payment_country assigned to the payment
        $paymentItemContainer = new PaymentItemContainer();
        $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($st));
        // remove VAT
        foreach ($paymentItemContainer->items() as $paymentItem) {
            $paymentItem->forcePrice($paymentItem->unitPriceWithoutVAT());
            $paymentItem->forceVat(0);
        }

        $payment1 = $this->makePayment($user, $st, $paymentItemContainer);
        $this->assertFalse($this->vatProcessor->isReverseChargePayment($payment1));
        $this->assertPaymentItemsEqualTo($payment1, $subscriptionTypeItemsReverseChargePrices);

        // Now add invoice address with foreign address with valid VAT ID
        $this->addCompanyInvoiceAddress($user, $this->franceCountry, 'some_company_id', 'some_vat_id');
        $this->mockEuVatValidator('FR', 'some_vat_id', true);

        $st = $this->increaseVat($st, 5);

        // First recurrent (true reverse-charge)
        $payment2 = $this->makeRecurrentPayment($payment1);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment2));
        $this->assertPaymentItemsEqualTo($payment2, $subscriptionTypeItemsReverseChargePricesVatIncreased);

        // Second recurrent (true reverse-charge)
        $payment3 = $this->makeRecurrentPayment($payment2);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment3));
        $this->assertPaymentItemsEqualTo($payment3, $subscriptionTypeItemsReverseChargePricesVatIncreased);
    }

    /**
     * Scenario:
     * - we manually applied reverse-charge on a payment in the past, however VAT was NOT subtracted from payment amount
     * - before first recurrent payment a valid B2B invoice address is assigned to the user
     * - recurrent payment is done in proper reverse-charge mode
     */
    public function testFirstPaymentUnmarkedReverseChargeVatKept()
    {
        $franceCountry = $this->countriesRepository->findByIsoCode('FR'); // some EU country
        $this->vatRatesRepository->upsert($this->countriesRepository->defaultCountry(), 20, 10, 10);
        $this->vatRatesRepository->upsert($franceCountry, 5, 5, 5);

        $user = $this->createUser();

        $subscriptionTypeItems = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 20],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 10],
        ];
        $subscriptionTypeItemsZeroVat = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 0],
        ];
        $subscriptionTypeItemsReverseChargePrices = [
            'web' => ['name' => 'web', 'amount' => 33.33, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 54.55, 'vat' => 0],
        ];
        $st = $this->createSubscriptionType(['web', 'print'], $subscriptionTypeItems);

        // First payment - fake reverse-charge (VAT not subtracted)
        // OSS is not applied since there is no payment_country assigned to the payment
        $paymentItemContainer = new PaymentItemContainer();
        $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($st));
        foreach ($paymentItemContainer->items() as $paymentItem) {
            $paymentItem->forceVat(0);
        }
        $payment1 = $this->makePayment($user, $st, $paymentItemContainer);
        $this->assertFalse($this->vatProcessor->isReverseChargePayment($payment1));
        $this->assertPaymentItemsEqualTo($payment1, $subscriptionTypeItemsZeroVat);

        // Now add invoice address with foreign address with valid VAT ID
        $this->addCompanyInvoiceAddress($user, $franceCountry, 'some_company_id', 'some_vat_id');
        $this->mockEuVatValidator('FR', 'some_vat_id', true);

        // First recurrent (true reverse-charge)
        $payment2 = $this->makeRecurrentPayment($payment1);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment2));
        $this->assertPaymentItemsEqualTo($payment2, $subscriptionTypeItemsReverseChargePrices);

        // Second recurrent (true reverse-charge)
        $payment3 = $this->makeRecurrentPayment($payment2);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment3));
        $this->assertPaymentItemsEqualTo($payment3, $subscriptionTypeItemsReverseChargePrices);
    }

    public function testFirstPaymentUnmarkedReverseChargeVatKeptWithVatIncreased()
    {
        $franceCountry = $this->countriesRepository->findByIsoCode('FR'); // some EU country
        $this->vatRatesRepository->upsert($this->countriesRepository->defaultCountry(), 20, 10, 10);
        $this->vatRatesRepository->upsert($franceCountry, 5, 5, 5);

        $user = $this->createUser();

        $subscriptionTypeItems = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 20],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 10],
        ];
        $subscriptionTypeItemsZeroVat = [
            'web' => ['name' => 'web', 'amount' => 40, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 60, 'vat' => 0],
        ];
        $subscriptionTypeItemsReverseChargePricesVatIncreased = [
            'web' => ['name' => 'web', 'amount' => 32.26, 'vat' => 0],
            'print' => ['name' => 'print', 'amount' => 52.63, 'vat' => 0],
        ];
        $st = $this->createSubscriptionType(['web', 'print'], $subscriptionTypeItems);

        // First payment - fake reverse-charge (VAT not subtracted)
        // OSS is not applied since there is no payment_country assigned to the payment
        $paymentItemContainer = new PaymentItemContainer();
        $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($st));
        foreach ($paymentItemContainer->items() as $paymentItem) {
            $paymentItem->forceVat(0);
        }
        $payment1 = $this->makePayment($user, $st, $paymentItemContainer);
        $this->assertFalse($this->vatProcessor->isReverseChargePayment($payment1));
        $this->assertPaymentItemsEqualTo($payment1, $subscriptionTypeItemsZeroVat);

        // Now add invoice address with foreign address with valid VAT ID
        $this->addCompanyInvoiceAddress($user, $franceCountry, 'some_company_id', 'some_vat_id');
        $this->mockEuVatValidator('FR', 'some_vat_id', true);

        $st = $this->increaseVat($st, 4);

        // First recurrent (true reverse-charge)
        $payment2 = $this->makeRecurrentPayment($payment1);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment2));
        $this->assertPaymentItemsEqualTo($payment2, $subscriptionTypeItemsReverseChargePricesVatIncreased);

        // Second recurrent (true reverse-charge)
        $payment3 = $this->makeRecurrentPayment($payment2);
        $this->assertTrue($this->vatProcessor->isReverseChargePayment($payment3));
        $this->assertPaymentItemsEqualTo($payment3, $subscriptionTypeItemsReverseChargePricesVatIncreased);
    }

    private function mockEuVatValidator(string $countryCode, string $vatId, bool $validVatIdResponse = false): void
    {
        $validator = \Mockery::mock(EuVatValidator::class)
            ->shouldReceive('validateVat')
            ->andReturn(new CheckVatResponse([
                'countryCode' => $countryCode,
                'vatNumber' => $vatId,
                'requestDate' => new \DateTime(),
                'valid' => $validVatIdResponse,
            ]))
            ->getMock();

        $vatModeDetector = $this->inject(VatModeDetector::class);
        $vatModeDetector->setEuVatValidator($validator);
    }

    private function makePayment(
        ActiveRow $user,
        ActiveRow $subscriptionType,
        PaymentItemContainer $paymentItemContainer,
        ?ActiveRow $paymentCountry = null,
    ): ActiveRow {
        $payment = $this->paymentsRepository->add(
            subscriptionType: $subscriptionType,
            paymentGateway: $this->paymentGateway,
            user: $user,
            paymentItemContainer: $paymentItemContainer,
            paymentCountry: $paymentCountry
        );

        // Make manual payment
        $this->inject(PaymentProcessor::class)->complete($payment, fn() => null);
        return $this->paymentsRepository->find($payment->id);
    }

    private function makeRecurrentPayment(ActiveRow $parentPayment): ActiveRow
    {
        $recurrent = $this->recurrentPaymentsRepository->recurrent($parentPayment);

        /** @var PaymentData $paymentData */
        $paymentData = $this->recurrentPaymentsResolver->resolvePaymentData($recurrent);

        $payment = $this->paymentsRepository->add(
            subscriptionType: $paymentData->subscriptionType,
            paymentGateway: $this->paymentGateway,
            user: $recurrent->user,
            paymentItemContainer: $paymentData->paymentItemContainer,
            recurrentCharge: true,
            paymentCountry: $paymentData->paymentCountry,
        );

        $this->recurrentPaymentsRepository->update($recurrent, [
            'payment_id' => $payment->id,
        ]);

        $this->inject(PaymentProcessor::class)->complete($payment, fn() => null);
        $payment = $this->paymentsRepository->find($payment->id);
        $this->recurrentPaymentsRepository->setCharged($recurrent, $payment, 'OK', 'OK');
        return $payment;
    }

    private function createUser(string $email = 'company@example.com'): ActiveRow
    {
        /** @var UserBuilder $userBuilder */
        $userBuilder = $this->inject(UserBuilder::class);
        $userRow = $userBuilder->createNew()
            ->setEmail($email)
            ->setPublicName($email)
            ->setPassword('secret', false)
            ->save();
        return $userRow;
    }

    private function increaseVat(ActiveRow $subscriptionType, float $increaseBy): ActiveRow
    {
        foreach ($subscriptionType->related('subscription_type_items') as $subscriptionTypeItem) {
            $this->subscriptionTypeItemsRepository->update($subscriptionTypeItem, [
                'vat' => $subscriptionTypeItem->vat + $increaseBy,
            ], true);
        }
        return $this->subscriptionTypesRepository->find($subscriptionType->id);
    }

    private function createSubscriptionType(
        string|array $contentAccess,
        array $subscriptionTypeItems = [],
        string $code = 'test_subscription',
        int $length = 31,
    ) {
        if (is_string($contentAccess)) {
            $contentAccess = [$contentAccess];
        }

        /** @var SubscriptionTypeBuilder $stb */
        $stb = $this->inject(SubscriptionTypeBuilder::class);
        $builder = $stb->createNew()
            ->setName($code)
            ->setCode($code)
            ->setUserLabel('')
            ->setActive(true)
            ->setLength($length)
            ->setContentAccessOption(...$contentAccess);

        $totalPrice = 0;
        foreach ($subscriptionTypeItems as $subscriptionTypeItem) {
            $totalPrice += $subscriptionTypeItem['amount'];
            $builder->addSubscriptionTypeItem(
                name: $subscriptionTypeItem['name'] ?? null,
                amount: $subscriptionTypeItem['amount'] ?? null,
                vat: $subscriptionTypeItem['vat'] ?? null,
                meta: $subscriptionTypeItem['meta'] ?? [],
            );
        }
        $builder->setPrice($totalPrice);

        return $builder->save();
    }

    private function addCompanyInvoiceAddress(
        ActiveRow $user,
        ActiveRow $country,
        ?string $companyId = null,
        ?string $companyVatId = null
    ): ActiveRow {
        $old = $this->addressesRepository->userAddresses($user, 'invoice')->fetch();
        if ($old) {
            $this->addressesRepository->softDelete($old, true);
        }

        return $this->addressesRepository->add(
            user: $user,
            type: 'invoice',
            firstName: $user->email,
            lastName: $user->email,
            address: 'Sample street',
            number: '123',
            city: 'Sample city',
            zip: '12345',
            countryId: $country->id,
            phoneNumber: '1234567890',
            companyId: $companyId,
            companyVatId: $companyVatId,
        );
    }
}
