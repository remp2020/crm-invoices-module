<?php

namespace Crm\InvoicesModule\Tests;

use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\Seeders\CountriesSeeder;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\InvoicesModule\InvoicesModule;
use Crm\InvoicesModule\Repository\InvoiceNumbersRepository;
use Crm\InvoicesModule\Repository\InvoicesRepository;
use Crm\InvoicesModule\Seeders\AddressTypesSeeder;
use Crm\InvoicesModule\Seeders\ConfigsSeeder;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentMetaRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\AddressTypesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Crm\UsersModule\Repository\LoginAttemptsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\Tests\TestNotificationHandler;
use Kdyby\Translation\Translator;
use League\Event\Emitter;

abstract class BaseTestCase extends DatabaseTestCase
{
    /** @var Emitter */
    protected $emitter;

    /** @var TestNotificationHandler */
    protected $testNotificationHandler;

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            LoginAttemptsRepository::class,
            ConfigsRepository::class,
            // To work with subscriptions, we need all these tables
            SubscriptionsRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionExtensionMethodsRepository::class,
            SubscriptionLengthMethodsRepository::class,
            // Payments + recurrent payments
            PaymentGatewaysRepository::class,
            PaymentItemsRepository::class,
            PaymentsRepository::class,
            PaymentMetaRepository::class,
            // Invoices
            InvoicesRepository::class,
            InvoiceNumbersRepository::class,
            AddressesRepository::class,
            AddressTypesRepository::class,
            CountriesRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
            CountriesSeeder::class,
            AddressTypesSeeder::class,
            ConfigsSeeder::class
        ];
    }

    protected function setUp(): void
    {
        $this->refreshContainer();
        parent::setUp();

        $this->emitter = $this->inject(Emitter::class);

        // Email notification is going to be handled by test handler
        $this->testNotificationHandler = new TestNotificationHandler();
        $this->emitter->addListener(NotificationEvent::class, $this->testNotificationHandler);

        // Register required event handlers
        $invoicesModule = new InvoicesModule($this->container, $this->inject(Translator::class));
        $invoicesModule->registerEventHandlers($this->emitter);
    }
}
