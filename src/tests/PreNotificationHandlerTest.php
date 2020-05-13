<?php

namespace Crm\InvoicesModule\Tests;

use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\InvoicesModule\Events\PreNotificationEventHandler;
use Crm\PaymentsModule\Events\PaymentChangeStatusEvent;
use Crm\PaymentsModule\Events\PaymentStatusChangeHandler;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentMetaRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Generator\SubscriptionsGenerator;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Events\PreNotificationEvent;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use Nette\Utils\DateTime;

class PreNotificationHandlerTest extends BaseTestCase
{
    /** @var UserManager */
    private $userManager;

    /** @var UsersRepository */
    private $usersRepository;

    /** @var SubscriptionTypeBuilder */
    private $subscriptionTypeBuilder;

    /** @var SubscriptionsGenerator */
    private $subscriptionGenerator;

    /** @var PaymentsRepository */
    private $paymentsRepository;

    /** @var PaymentMetaRepository */
    private $paymentMetaRepository;

    /** @var ConfigsRepository */
    private $configsRepository;

    private $paymentGateway;

    /** @var AddressesRepository */
    private $addressesRepository;

    private $subscriptionType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->emitter = $this->inject(Emitter::class);
        $this->userManager = $this->inject(UserManager::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $this->subscriptionGenerator = $this->inject(SubscriptionsGenerator::class);
        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $this->paymentMetaRepository = $this->getRepository(PaymentMetaRepository::class);
        $this->addressesRepository = $this->getRepository(AddressesRepository::class);
        $this->configsRepository = $this->getRepository(ConfigsRepository::class);

        $pgr = $this->getRepository(PaymentGatewaysRepository::class);
        $this->paymentGateway = $pgr->add('test', 'test', 10, true, true);

        // To create subscriptions from payments, register listener
        $this->emitter->addListener(PaymentChangeStatusEvent::class, $this->inject(PaymentStatusChangeHandler::class));
        // For pre-notification processing
        $this->emitter->addListener(PreNotificationEvent::class, $this->inject(PreNotificationEventHandler::class));
    }

    /**
     * This test generates PDF file as an attachment
     * @group slow
     */
    public function testAddingContextAndAttachmentsNotAllowed()
    {
        $this->setAllowAttachConfig(false);
        $subscriptionType = $this->getSubscriptionType();

        $user = $this->userWithRegDate('test@example.com');
        $payment = $this->addPayment($user, $subscriptionType, '2021-01-01 01:00:00');
        $this->addressesRepository->add($user, 'invoice', 'a', 'a', 'a', 'a', 'a', 'a', 1, 'a');
        $this->paymentMetaRepository->add($payment, 'invoiceable', 1);

        $subscription = $payment->subscription;

        $templateParams = [
            'payment' => $payment->toArray(),
            'subscription' => $subscription->toArray(),
        ];
        /** @var NotificationEvent $event */
        $event = $this->emitter->emit(new NotificationEvent($this->emitter, $user, 'template_example', $templateParams));

        $this->assertEmpty($event->getAttachments());
    }

    /**
     * This test generates PDF file as an attachment
     * @group slow
     */
    public function testAddingContextAndAttachmentsAllowed()
    {
        $this->setAllowAttachConfig(true);
        $subscriptionType = $this->getSubscriptionType();

        $user = $this->userWithRegDate('test@example.com');
        $payment = $this->addPayment($user, $subscriptionType, '2021-01-01 01:00:00');
        $this->addressesRepository->add($user, 'invoice', 'a', 'a', 'a', 'a', 'a', 'a', 1, 'a');
        $this->paymentMetaRepository->add($payment, 'invoiceable', 1);

        $subscription = $payment->subscription;

        $templateParams = [
            'payment' => $payment->toArray(),
            'subscription' => $subscription->toArray(),
        ];
        /** @var NotificationEvent $event */
        $event = $this->emitter->emit(new NotificationEvent($this->emitter, $user, 'template_example', $templateParams));

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
            'value' => $allow
        ]);
    }

    private function addPayment($user, $subscriptionType, $paidAtString, $startSubscriptionAtString = '2021-01-01 01:00:00')
    {
        $payment = $this->paymentsRepository->add($subscriptionType, $this->paymentGateway, $user, new PaymentItemContainer(), null, 1, new DateTime($startSubscriptionAtString));
        $this->paymentsRepository->updateStatus($payment, PaymentsRepository::STATUS_PAID);
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
