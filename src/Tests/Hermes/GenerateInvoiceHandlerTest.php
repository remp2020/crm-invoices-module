<?php

namespace Crm\InvoicesModule\Tests\Hermes;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Repositories\ConfigCategoriesRepository;
use Crm\ApplicationModule\Repositories\ConfigsRepository;
use Crm\ApplicationModule\Seeders\ConfigsSeeder;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\InvoicesModule\Hermes\GenerateInvoiceHandler;
use Crm\InvoicesModule\Models\InvoiceNumber\InvoiceNumber;
use Crm\InvoicesModule\Repositories\InvoiceItemsRepository;
use Crm\InvoicesModule\Repositories\InvoiceNumbersRepository;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\InvoicesModule\Seeders\AddressTypesSeeder;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentItemMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentItemsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\VariableSymbolRepository;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeItemsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\AddressTypesRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
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
            VariableSymbolRepository::class,
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

    public function testPaymentHasInvoice()
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

        // add invoice to payment
        /** @var InvoiceNumber $invoiceNumber */
        $invoiceNumber = $this->inject(InvoiceNumber::class);
        $nextInvoiceNumber = $invoiceNumber->getNextInvoiceNumber($payment);
        $this->paymentsRepository->update($payment, ['invoice_number_id' => $nextInvoiceNumber->id]);
        $invoice = $this->invoicesRepository->add($this->getUser(), $payment);
        $invoiceLastUpdated = $invoice->updated_date; // keep this for later assert
        $this->paymentsRepository->update($payment, ['invoice_id' => $invoice->id]);
        $this->assertEquals(1, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(1, $this->invoicesRepository->totalCount());

        $message = new HermesMessage('generate_invoice', [
            'payment_id' => $payment->id
        ]);

        // *******************************************************************
        // test checks start here
        $result = $this->generateInvoiceHandler->handle($message);
        $this->assertTrue($result);

        // no new invoice number or invoice generated
        $this->assertEquals(1, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(1, $this->invoicesRepository->totalCount());

        // and previous invoice not updated
        $invoice = $this->invoicesRepository->find($invoice->id);
        $this->assertEquals($invoiceLastUpdated, $invoice->updated_date);
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

        // no invoice number or invoice generated
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
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
            $this->paymentsRepository->updateStatus($payment, PaymentStatusEnum::Paid->value);
            $this->paymentsRepository->update($payment, ['paid_at' => $paidAt]);
        }

        return $this->paymentsRepository->find($payment->id);
    }
}
