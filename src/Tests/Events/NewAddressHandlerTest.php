<?php

namespace Crm\InvoicesModule\Tests\Events;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\InvoicesModule\Events\NewAddressHandler;
use Crm\InvoicesModule\Hermes\GenerateInvoiceHandler;
use Crm\InvoicesModule\Seeders\AddressTypesSeeder;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentItemsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\UsersModule\Events\AddressChangedEvent;
use Crm\UsersModule\Events\NewAddressEvent;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\AddressTypesRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\MessageInterface;

class NewAddressHandlerTest extends DatabaseTestCase
{
    private Dispatcher $dispatcher;

    private NewAddressHandler $newAddressHandler;

    private UsersRepository $usersRepository;

    private ?ActiveRow $paymentGateway = null;
    private ?ActiveRow $user = null;

    protected function requiredRepositories(): array
    {
        return [
            AddressesRepository::class,
            AddressTypesRepository::class,
            CountriesRepository::class,
            PaymentGatewaysRepository::class,
            PaymentsRepository::class,
            PaymentItemsRepository::class,
            UsersRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            AddressTypesSeeder::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();


        // register own dispatcher because dispatcher initialized by Nette's DI keeps all registered handlers (no way to remove them)
        $this->dispatcher = new Dispatcher($this->inject(DriverInterface::class));
        $this->newAddressHandler = $this->inject(NewAddressHandler::class);

        $this->usersRepository = $this->getRepository(UsersRepository::class);
    }

    public function testSuccessUser()
    {
        $user = $this->getUser();
        $this->assertEquals(1, $user->invoice);
        $this->assertEquals(0, $user->disable_auto_invoice);

        $address = $this->addUserAddress('invoice');
        $payment = $this->addPayment($user, new DateTime(), new DateTime());

        $event = new NewAddressEvent($address, $admin = false);

        // set observer (mocked handler) to observe hermes handler
        // whole GenerateInvoiceHandler is tested by separate class GenerateInvoiceHandlerTest
        $generateInvoiceHandlerObserver = $this->createMock(GenerateInvoiceHandler::class);
        // handler should be received only once and should contain correct payment id
        $generateInvoiceHandlerObserver->expects($this->once())
            ->method('handle')
            ->with(
                $this->callback(function (MessageInterface $hermesMessage) use ($payment) {
                    if ($hermesMessage->getType() !== 'generate_invoice') {
                        return false;
                    }
                    $payload = $hermesMessage->getPayload();
                    if (!isset($payload['payment_id'])) {
                        return false;
                    }

                    if ($payload['payment_id'] !== $payment->id) {
                        return false;
                    }
                    return true;
                }),
            );

        // register observer as hermes handler
        $this->dispatcher->registerHandler(
            'generate_invoice',
            $generateInvoiceHandlerObserver,
        );

        // handle league & hermes events
        $this->newAddressHandler->handle($event);
        $this->dispatcher->handle();

        // no change to user's invoicing settings
        $user = $this->usersRepository->find($user->id);
        $this->assertEquals(1, $user->invoice);
        $this->assertEquals(0, $user->disable_auto_invoice);
    }


    public function testSuccessAdmin()
    {
        $user = $this->getUser();
        // disable auto invoicing; update by admin should enable it automatically
        $this->usersRepository->update($user, ['invoice' => false, 'disable_auto_invoice' => true]);
        $user = $this->usersRepository->find($user->id);
        $this->assertEquals(0, $user->invoice);
        $this->assertEquals(1, $user->disable_auto_invoice);

        $address = $this->addUserAddress('invoice');
        $payment = $this->addPayment($user, new DateTime(), new DateTime());

        $event = new NewAddressEvent($address, $admin = true);

        // set observer (mocked handler) to observe hermes handler
        // whole GenerateInvoiceHandler is tested by separate class GenerateInvoiceHandlerTest
        $generateInvoiceHandlerObserver = $this->createMock(GenerateInvoiceHandler::class);
        // handler should be received only once and should contain correct payment id
        $generateInvoiceHandlerObserver->expects($this->once())
            ->method('handle')
            ->with(
                $this->callback(function (MessageInterface $hermesMessage) use ($payment) {
                    if ($hermesMessage->getType() !== 'generate_invoice') {
                        return false;
                    }
                    $payload = $hermesMessage->getPayload();
                    if (!isset($payload['payment_id'])) {
                        return false;
                    }

                    if ($payload['payment_id'] !== $payment->id) {
                        return false;
                    }
                    return true;
                }),
            );

        // register observer as hermes handler
        $this->dispatcher->registerHandler(
            'generate_invoice',
            $generateInvoiceHandlerObserver,
        );

        // handle league & hermes events
        $this->newAddressHandler->handle($event);
        $this->dispatcher->handle();

        // changed to enabled invoicing
        $user = $this->usersRepository->find($user->id);
        $this->assertEquals(1, $user->invoice);
        $this->assertEquals(0, $user->disable_auto_invoice);
    }

    public function testAddressIncorrectType()
    {
        $user = $this->getUser();
        // add address that is not an invoice address type
        /** @var AddressTypesRepository $addressTypesRepository */
        $addressTypesRepository = $this->inject(AddressTypesRepository::class);
        $addressTypesRepository->add('not-an-invoice-type', 'Not an invoice address type');
        $address = $this->addUserAddress('not-an-invoice-type');
        $this->addPayment($user, new DateTime(), new DateTime());

        $event = new NewAddressEvent($address);

        // set observer (mocked handler) to observe hermes handler
        // whole GenerateInvoiceHandler is tested by separate class GenerateInvoiceHandlerTest
        $generateInvoiceHandlerObserver = $this->createMock(GenerateInvoiceHandler::class);
        // handler shouldn't be called
        $generateInvoiceHandlerObserver->expects($this->never())
            ->method('handle');

        // register observer as hermes handler
        $this->dispatcher->registerHandler(
            'generate_invoice',
            $generateInvoiceHandlerObserver,
        );

        // handle league & hermes events
        $this->newAddressHandler->handle($event);
        $this->dispatcher->handle();
    }

    public function testNoPayments()
    {
        $this->getUser();
        $address = $this->addUserAddress('invoice');

        // check if there are really no payments
        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->inject(PaymentsRepository::class);
        $this->assertEquals(0, $paymentsRepository->totalCount());

        $event = new NewAddressEvent($address);

        // set observer (mocked handler) to observe hermes handler
        // whole GenerateInvoiceHandler is tested by separate class GenerateInvoiceHandlerTest
        $generateInvoiceHandlerObserver = $this->createMock(GenerateInvoiceHandler::class);
        // handler shouldn't be called
        $generateInvoiceHandlerObserver->expects($this->never())
            ->method('handle');

        // register observer as hermes handler
        $this->dispatcher->registerHandler(
            'generate_invoice',
            $generateInvoiceHandlerObserver,
        );

        // handle league & hermes events
        $this->newAddressHandler->handle($event);
        $this->dispatcher->handle();
    }

    public function testIncorrectEventType()
    {
        $address = $this->addUserAddress('invoice');
        $event = new AddressChangedEvent($address);

        $this->expectExceptionObject(new \Exception(
            'Invalid type of event. Expected: [Crm\UsersModule\Events\NewAddressEvent]. Received: [Crm\UsersModule\Events\AddressChangedEvent].',
        ));

        // handle event
        $this->newAddressHandler->handle($event);
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

        $user = $userManager->addNewUser('example@example.com', false, 'unknown', null, false);
        $this->usersRepository->update($user, [
            'invoice' => true,
            'disable_auto_invoice' => false,
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
        ?DateTime $paidAt = null,
    ): ActiveRow {
        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->getRepository(PaymentsRepository::class);

        $payment = $paymentsRepository->add(
            null,
            $this->getPaymentGateway(),
            $user,
            new PaymentItemContainer(),
            null,
            1,
            $startSubscriptionAt,
        );

        if ($paidAt !== null) {
            $paymentsRepository->updateStatus($payment, PaymentStatusEnum::Paid->value);
            $paymentsRepository->update($payment, ['paid_at' => $paidAt]);
        }

        return $paymentsRepository->find($payment->id);
    }

    private function addUserAddress(string $addressType): ActiveRow
    {
        /** @var CountriesRepository $countriesRepository */
        $countriesRepository = $this->getRepository(CountriesRepository::class);
        $country = $countriesRepository->add('SK', 'Slovensko', null);

        /** @var AddressesRepository $addressesRepository */
        $addressesRepository = $this->inject(AddressesRepository::class);
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
}
