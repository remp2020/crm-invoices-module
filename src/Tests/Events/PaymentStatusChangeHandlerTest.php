<?php

namespace Crm\InvoicesModule\Tests\Events;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Config\Repository\ConfigCategoriesRepository;
use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\InvoicesModule\Events\PaymentStatusChangeHandler;
use Crm\InvoicesModule\Hermes\GenerateInvoiceHandler;
use Crm\InvoicesModule\Seeders\ConfigsSeeder;
use Crm\PaymentsModule\Events\NewPaymentEvent;
use Crm\PaymentsModule\Events\PaymentChangeStatusEvent;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\MessageInterface;

class PaymentStatusChangeHandlerTest extends DatabaseTestCase
{
    private ApplicationConfig $applicationConfig;

    private ConfigsRepository $configsRepository;

    private Dispatcher $dispatcher;

    private PaymentStatusChangeHandler $paymentStatusChangeHandler;

    private ?ActiveRow $paymentGateway = null;
    private ?ActiveRow $user = null;

    protected function requiredRepositories(): array
    {
        return [
            ConfigCategoriesRepository::class,
            ConfigsRepository::class,
            PaymentGatewaysRepository::class,
            PaymentsRepository::class,
            PaymentItemsRepository::class,
            UsersRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            ConfigsSeeder::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->applicationConfig = $this->inject(ApplicationConfig::class);
        $this->configsRepository = $this->getRepository(ConfigsRepository::class);

        // register own dispatcher because dispatcher initialized by Nette's DI keeps all registered handlers (no way to remove them)
        $this->dispatcher = new Dispatcher($this->inject(DriverInterface::class));

        $this->paymentStatusChangeHandler = $this->inject(PaymentStatusChangeHandler::class);
    }

    public function testSuccess()
    {
        // assure that invoice generation is enabled in config
        $generateInvoiceAfterPaymentFlag = $this->configsRepository->findBy('name', 'generate_invoice_after_payment');
        $this->configsRepository->update($generateInvoiceAfterPaymentFlag, ['value' => true]);
        $this->assertTrue(filter_var($this->applicationConfig->get('generate_invoice_after_payment'), FILTER_VALIDATE_BOOLEAN));

        $user = $this->getUser();
        $payment = $this->addPayment($user, new DateTime(), new DateTime());
        $this->assertNotNull($payment->paid_at);

        $event = new PaymentChangeStatusEvent($payment);

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
                })
            );

        // register observer as hermes handler
        $this->dispatcher->registerHandler(
            'generate_invoice',
            $generateInvoiceHandlerObserver
        );

        // handle league & hermes events
        $this->paymentStatusChangeHandler->handle($event);
        $this->dispatcher->handle();
    }

    public function testPaymentNotPaid()
    {
        $user = $this->getUser();
        $payment = $this->addPayment($user, new DateTime());
        $this->assertNull($payment->paid_at);

        $event = new PaymentChangeStatusEvent($payment);

        // set observer (mocked handler) to observe hermes handler
        // whole GenerateInvoiceHandler is tested by separate class GenerateInvoiceHandlerTest
        $generateInvoiceHandlerObserver = $this->createMock(GenerateInvoiceHandler::class);
        // handler shouldn't be called
        $generateInvoiceHandlerObserver->expects($this->never())
            ->method('handle');

        // register observer as hermes handler
        $this->dispatcher->registerHandler(
            'generate_invoice',
            $generateInvoiceHandlerObserver
        );

        // handle league & hermes events
        $this->paymentStatusChangeHandler->handle($event);
        $this->dispatcher->handle();
    }

    public function testDisabledConfigGenerateInvoiceAfterPayment()
    {
        // assure that invoice generation is disabled in config
        $generateInvoiceAfterPaymentFlag = $this->configsRepository->findBy('name', 'generate_invoice_after_payment');
        $this->configsRepository->update($generateInvoiceAfterPaymentFlag, ['value' => false]);
        $this->assertFalse(filter_var($this->applicationConfig->get('generate_invoice_after_payment'), FILTER_VALIDATE_BOOLEAN));

        $user = $this->getUser();
        $payment = $this->addPayment($user, new DateTime(), new DateTime());
        $this->assertNotNull($payment->paid_at);

        $event = new PaymentChangeStatusEvent($payment);

        // set observer (mocked handler) to observe hermes handler
        // whole GenerateInvoiceHandler is tested by separate class GenerateInvoiceHandlerTest
        $generateInvoiceHandlerObserver = $this->createMock(GenerateInvoiceHandler::class);
        // handler shouldn't be called
        $generateInvoiceHandlerObserver->expects($this->never())
            ->method('handle');

        // register observer as hermes handler
        $this->dispatcher->registerHandler(
            'generate_invoice',
            $generateInvoiceHandlerObserver
        );

        // handle league & hermes events
        $this->paymentStatusChangeHandler->handle($event);
        $this->dispatcher->handle();
    }

    public function testIncorrectEventType()
    {
        $user = $this->getUser();
        $payment = $this->addPayment($user, new DateTime());
        $event = new NewPaymentEvent($payment);

        $this->expectExceptionObject(new \Exception(
            'Invalid type of event. Expected: [Crm\PaymentsModule\Events\PaymentChangeStatusEvent]. Received: [Crm\PaymentsModule\Events\NewPaymentEvent].'
        ));

        // handle league event
        $this->paymentStatusChangeHandler->handle($event);
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
        ?DateTime $paidAt = null
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
            $startSubscriptionAt
        );

        if ($paidAt !== null) {
            $paymentsRepository->updateStatus($payment, PaymentsRepository::STATUS_PAID);
            $paymentsRepository->update($payment, ['paid_at' => $paidAt]);
        }

        return $paymentsRepository->find($payment->id);
    }
}
