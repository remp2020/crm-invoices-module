<?php

namespace Crm\InvoicesModule\Tests;

use Crm\ApplicationModule\Models\Event\LazyEventEmitter;
use Crm\ApplicationModule\Repositories\ConfigsRepository;
use Crm\InvoicesModule\Events\PreNotificationEventHandler;
use Crm\PaymentsModule\Events\PaymentChangeStatusEvent;
use Crm\PaymentsModule\Events\PaymentStatusChangeHandler;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Tests\Gateways\TestRecurrentGateway;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\UsersModule\Events\NotificationContext;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Events\PreNotificationEvent;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Utils\DateTime;

class PreNotificationHandlerTest extends BaseTestCase
{
    private LazyEventEmitter $lazyEventEmitter;

    private UserManager $userManager;

    private UsersRepository $usersRepository;

    private SubscriptionTypeBuilder $subscriptionTypeBuilder;

    private PaymentsRepository $paymentsRepository;

    private PaymentMetaRepository $paymentMetaRepository;

    private ConfigsRepository $configsRepository;

    private AddressesRepository $addressesRepository;

    private CountriesRepository $countriesRepository;

    private $paymentGateway;

    private $subscriptionType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lazyEventEmitter = $this->inject(LazyEventEmitter::class);
        $this->userManager = $this->inject(UserManager::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $this->paymentMetaRepository = $this->getRepository(PaymentMetaRepository::class);
        $this->addressesRepository = $this->getRepository(AddressesRepository::class);
        $this->configsRepository = $this->getRepository(ConfigsRepository::class);
        $this->countriesRepository = $this->getRepository(CountriesRepository::class);

        /** @var PaymentGatewaysRepository $pgr */
        $pgr = $this->getRepository(PaymentGatewaysRepository::class);
        $this->paymentGateway = $pgr->findByCode(TestRecurrentGateway::GATEWAY_CODE);

        // To create subscriptions from payments, register listener
        $this->lazyEventEmitter->addListener(PaymentChangeStatusEvent::class, $this->inject(PaymentStatusChangeHandler::class));
        // For pre-notification processing
        $this->lazyEventEmitter->addListener(PreNotificationEvent::class, $this->inject(PreNotificationEventHandler::class));
    }

    protected function tearDown(): void
    {
        $this->lazyEventEmitter->removeAllListeners(PaymentChangeStatusEvent::class);
        $this->lazyEventEmitter->removeAllListeners(PreNotificationEvent::class);

        parent::tearDown();
    }

    /**
     * This test generates PDF file as an attachment
     */
    public function testAddingContextAndAttachmentsNotAllowed()
    {
        $this->setAllowAttachConfig(false);
        $subscriptionType = $this->getSubscriptionType();

        $user = $this->userWithRegDate('test@example.com');
        $payment = $this->addPayment($user, $subscriptionType, '2021-01-01 01:00:00');
        $country = $this->countriesRepository->findByIsoCode('SK');
        $this->addressesRepository->add($user, 'invoice', 'a', 'a', 'a', 'a', 'a', 'a', $country->id, 'a');
        $this->paymentMetaRepository->add($payment, 'invoiceable', 1);

        $subscription = $payment->subscription;

        $templateParams = [
            'payment' => $payment->toArray(),
            'subscription' => $subscription->toArray(),
        ];
        /** @var NotificationEvent $event */
        $event = $this->lazyEventEmitter->emit(new NotificationEvent($this->lazyEventEmitter, $user, 'template_example', $templateParams));

        $this->assertEmpty($event->getAttachments());
    }

    /**
     * This test generates PDF file as an attachment
     */
    public function testAddingContextAndAttachmentsAllowed()
    {
        $this->setAllowAttachConfig(true);
        $subscriptionType = $this->getSubscriptionType();

        $user = $this->userWithRegDate('test@example.com');
        $payment = $this->addPayment($user, $subscriptionType, 'now');
        $country = $this->countriesRepository->findByIsoCode('SK');
        $this->addressesRepository->add($user, 'invoice', 'a', 'a', 'a', 'a', 'a', 'a', $country->id, 'a');
        $this->paymentMetaRepository->add($payment, 'invoiceable', 1);

        $subscription = $payment->subscription;

        $templateParams = [
            'payment' => $payment->toArray(),
            'subscription' => $subscription->toArray(),
        ];

        /** @var PreNotificationEventHandler $preNotificationHandler */
        $preNotificationHandler = $this->inject(PreNotificationEventHandler::class);
        $preNotificationHandler->enableForNotificationHermesTypes('foo');

        /** @var NotificationEvent $event */
        $event = $this->lazyEventEmitter->emit(
            new NotificationEvent(
                $this->lazyEventEmitter,
                $user,
                'template_example',
                $templateParams,
                null,
                [],
                null,
                new NotificationContext([
                    NotificationContext::HERMES_MESSAGE_TYPE => 'foo',
                ]),
            ),
        );

        $this->assertNotEmpty($event->getAttachments());

        // Assert invoice attachment was added
        $invoiceAttachment = $event->getAttachments()[0];
        $this->assertTrue(isset($invoiceAttachment['content']));
        $this->assertTrue(isset($invoiceAttachment['file']));
    }

    private function getSubscriptionType()
    {
        if ($this->subscriptionType) {
            return $this->subscriptionType;
        }

        return $this->subscriptionType = $this->subscriptionTypeBuilder
            ->createNew()
            ->setName('test_subscription')
            ->setUserLabel('')
            ->setActive(true)
            ->setPrice(1)
            ->setLength(365)
            ->save();
    }

    private function setAllowAttachConfig($allow = true)
    {
        $config = $this->configsRepository->loadByName('attach_invoice_to_payment_notification');
        $this->configsRepository->update($config, [
            'value' => $allow,
        ]);
    }

    private function addPayment($user, $subscriptionType, $paidAtString, $startSubscriptionAtString = '2021-01-01 01:00:00')
    {
        $payment = $this->paymentsRepository->add($subscriptionType, $this->paymentGateway, $user, new PaymentItemContainer(), null, 1, new DateTime($startSubscriptionAtString));
        $this->paymentsRepository->updateStatus($payment, PaymentStatusEnum::Paid->value);
        $this->paymentsRepository->update($payment, ['paid_at' => new DateTime($paidAtString)]);
        return $this->paymentsRepository->find($payment->id);
    }

    private function userWithRegDate($email, $regDateString = '2020-01-01 01:00:00')
    {
        $user = $this->userManager->addNewUser($email, false, 'unknown', null, false);
        $this->usersRepository->update($user, [
            'created_at' => new DateTime($regDateString),
            'invoice' => 1,
        ]);
        return $user;
    }
}
