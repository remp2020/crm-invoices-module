<?php

namespace Crm\InvoicesModule\Tests;

use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\Seeders\CountriesSeeder;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
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
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Repository\AddressTypesRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Crm\UsersModule\Repository\LoginAttemptsRepository;
use Crm\UsersModule\Repository\UsersRepository;

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
