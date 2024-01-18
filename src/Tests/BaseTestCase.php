<?php

namespace Crm\InvoicesModule\Tests;

use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\Seeders\CountriesSeeder;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\InvoicesModule\Repositories\InvoiceNumbersRepository;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\InvoicesModule\Seeders\AddressTypesSeeder;
use Crm\InvoicesModule\Seeders\ConfigsSeeder;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentMetaRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Repositories\AddressTypesRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\UsersModule\Repositories\LoginAttemptsRepository;
use Crm\UsersModule\Repositories\UsersRepository;

abstract class BaseTestCase extends DatabaseTestCase
{
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
            ConfigsSeeder::class,
        ];
    }
}
