<?php

namespace Crm\InvoicesModule\Tests\Hermes;

use Crm\ApplicationModule\Config\Repository\ConfigCategoriesRepository;
use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Seeders\ConfigsSeeder;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\InvoicesModule\Hermes\GenerateInvoiceHandler;
use Crm\InvoicesModule\Repository\InvoiceItemsRepository;
use Crm\InvoicesModule\Repository\InvoiceNumbersRepository;
use Crm\InvoicesModule\Repository\InvoicesRepository;
use Crm\InvoicesModule\Seeders\AddressTypesSeeder;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentItemMetaRepository;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\VariableSymbol;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repository\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeItemsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Crm\UsersModule\Repository\AddressTypesRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class GenerateInvoiceHandlerTest extends DatabaseTestCase
{
    private GenerateInvoiceHandler $generateInvoiceHandler;

    private InvoiceNumbersRepository $invoiceNumbersRepository;
    private InvoicesRepository $invoicesRepository;
    private PaymentsRepository $paymentsRepository;

    private ?ActiveRow $paymentGateway = null;
    private ?ActiveRow $subscriptionType = null;
    private ?ActiveRow $user = null;

    protected function requiredRepositories(): array
    {
        return [
            AccessTokensRepository::class, // needed so tests can automatically delete users
            AddressesRepository::class,
            AddressTypesRepository::class,
            ConfigCategoriesRepository::class,
            ConfigsRepository::class,
            CountriesRepository::class,
            InvoiceItemsRepository::class,
            InvoiceNumbersRepository::class,
            InvoicesRepository::class,
            PaymentGatewaysRepository::class,
            PaymentsRepository::class,
            PaymentItemsRepository::class,
            PaymentItemMetaRepository::class,
            SubscriptionExtensionMethodsRepository::class,
            SubscriptionLengthMethodsRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionTypeItemsRepository::class,
            UsersRepository::class,
            VariableSymbol::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            AddressTypesSeeder::class,
            ConfigsSeeder::class,
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->invoiceNumbersRepository = $this->getRepository(InvoiceNumbersRepository::class);
        $this->invoicesRepository = $this->getRepository(InvoicesRepository::class);
        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);

        $this->generateInvoiceHandler = $this->inject(GenerateInvoiceHandler::class);
    }

    public function testSuccess()
    {
        $payment = $this->addPayment(
            $this->getUser(),
            $this->getSubscriptionType(),
            new DateTime(),
            new DateTime()
        );

        $this->addUserAddress('invoice');

        // checks before emit
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        $message = new HermesMessage('generate_invoice', [
            'payment_id' => $payment->id
        ]);

        // *******************************************************************
        // test checks start here
        $result = $this->generateInvoiceHandler->handle($message);
        $this->assertTrue($result);

        // exactly one invoice number and one invoice exists
        $this->assertEquals(1, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(1, $this->invoicesRepository->totalCount());
        // with correct variable symbol
        $invoice = $this->invoicesRepository->findBy('variable_symbol', $payment->variable_symbol);
        $this->assertNotNull($invoice);
    }

    public function testEmptyPayload()
    {
        // checks before emit
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // empty payload
        $message = new HermesMessage('generate_invoice', []);

        // *******************************************************************
        // test checks start here
        $result = $this->generateInvoiceHandler->handle($message);
        $this->assertFalse($result);

        // no invoices generated
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // empty payment_id
        $message = new HermesMessage('generate_invoice', [
            'payment_id' => null
        ]);
        $result = $this->generateInvoiceHandler->handle($message);
        $this->assertFalse($result);
        // no invoice number or invoice generated
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());
    }

    public function testInvalidPaymentId()
    {
        // checks before emit
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // assert there is no payment and use random ID
        $this->assertEquals(0, $this->paymentsRepository->totalCount());
        $message = new HermesMessage('generate_invoice', [
            'payment_id' => 123456
        ]);

        // *******************************************************************
        // test checks start here
        $result = $this->generateInvoiceHandler->handle($message);
        $this->assertFalse($result);

        // no invoice number or invoice generated
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());
    }

    public function testMissingAddress()
    {
        $payment = $this->addPayment(
            $this->getUser(),
            $this->getSubscriptionType(),
            new DateTime(),
            new DateTime()
        );

        // checks before emit
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        $message = new HermesMessage('generate_invoice', [
            'payment_id' => $payment->id
        ]);

        // *******************************************************************
        // test checks start here
        $result = $this->generateInvoiceHandler->handle($message);
        $this->assertFalse($result);

        // no invoice number or invoice generated
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());
    }

    public function testUserWithAddressButNotInvoiceType()
    {
        $payment = $this->addPayment(
            $this->getUser(),
            $this->getSubscriptionType(),
            new DateTime(),
            new DateTime()
        );

        // checks before emit
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // add address that is not an invoice address type
        /** @var AddressTypesRepository $addressTypesRepository */
        $addressTypesRepository = $this->inject(AddressTypesRepository::class);
        $addressTypesRepository->add('not-an-invoice-type', 'Not an invoice address type');
        $this->addUserAddress('not-an-invoice-type');

        $message = new HermesMessage('generate_invoice', [
            'payment_id' => $payment->id
        ]);

        // *******************************************************************
        // test checks start here
        $result = $this->generateInvoiceHandler->handle($message);
        $this->assertFalse($result);

        // no invoice number or invoice generated
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());
    }

    public function testInvalidPaymentState()
    {
        $payment = $this->addPayment(
            $this->getUser(),
            $this->getSubscriptionType(),
            new DateTime(),
            null // payment not paid
        );

        $this->addUserAddress('invoice');

        $message = new HermesMessage('generate_invoice', [
            'payment_id' => $payment->id
        ]);
        $result = $this->generateInvoiceHandler->handle($message);
        $this->assertFalse($result);
        // no invoices generated
        $this->assertEquals(0, $this->invoicesRepository->totalCount());
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
        $usersRepository = $this->inject(UsersRepository::class);

        $user = $userManager->addNewUser('example@example.com', false, 'unknown', null, false);
        $usersRepository->update($user, [
            'invoice' => 1,
        ]);
        return $this->user = $user;
    }

    private function addUserAddress(string $addressType): ActiveRow
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
        );
    }

    private function getSubscriptionType(): ActiveRow
    {
        if ($this->subscriptionType) {
            return $this->subscriptionType;
        }

        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        return $this->subscriptionType = $subscriptionTypeBuilder
            ->createNew()
            ->setName('test_subscription')
            ->setUserLabel('')
            ->setActive(true)
            ->setPrice(1)
            ->setLength(365)
            ->save();
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
        ActiveRow $subscriptionType,
        DateTime $startSubscriptionAt,
        ?DateTime $paidAt = null
    ): ActiveRow {
        $paymentItemContainer = (new PaymentItemContainer())
            ->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));

        $payment = $this->paymentsRepository->add(
            $subscriptionType,
            $this->getPaymentGateway(),
            $user,
            $paymentItemContainer,
            null,
            null,
            $startSubscriptionAt
        );

        if ($paidAt !== null) {
            $this->paymentsRepository->updateStatus($payment, PaymentsRepository::STATUS_PAID);
            $this->paymentsRepository->update($payment, ['paid_at' => $paidAt]);
        }

        return $this->paymentsRepository->find($payment->id);
    }
}
