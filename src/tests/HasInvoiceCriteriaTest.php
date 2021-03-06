<?php

namespace Crm\InvoicesModule\Tests;

use Crm\ApplicationModule\Seeders\CountriesSeeder;
use Crm\InvoicesModule\Repository\InvoiceNumbersRepository;
use Crm\InvoicesModule\Repository\InvoicesRepository;
use Crm\InvoicesModule\Scenarios\HasInvoiceCriteria;
use Crm\InvoicesModule\Seeders\AddressTypesSeeder;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Tests\PaymentsTestCase;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\AddressTypesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Nette\Utils\DateTime;

class HasInvoiceCriteriaTest extends PaymentsTestCase
{
    public function requiredRepositories(): array
    {
        $repositories = parent::requiredRepositories();
        $repositories[] = InvoicesRepository::class;
        $repositories[] = InvoiceNumbersRepository::class;
        $repositories[] = AddressesRepository::class;
        $repositories[] = CountriesRepository::class;
        $repositories[] = AddressTypesRepository::class;
        return $repositories;
    }

    public function requiredSeeders(): array
    {
        $seeders = parent::requiredSeeders();
        $seeders[] = CountriesSeeder::class;
        $seeders[] = AddressTypesSeeder::class;
        return $seeders;
    }

    public function dataProviderForTestHasInvoiceCriteria(): array
    {
        return [
            [1, true, true],
            [0, true, false],
            [1, false, false],
            [0, false, true],
        ];
    }

    /**
     * @dataProvider dataProviderForTestHasInvoiceCriteria
     */
    public function testHasInvoiceCriteria($hasInvoice, $shoudHaveInvoice, $expectedResult)
    {
        [$paymentSelection, $paymentRow] = $this->prepareData($hasInvoice);

        $criteria = $this->inject(HasInvoiceCriteria::class);
        $values = (object)['selection' => $shoudHaveInvoice];
        $criteria->addConditions($paymentSelection, [HasInvoiceCriteria::KEY => $values], $paymentRow);

        if ($expectedResult) {
            $this->assertNotFalse($paymentSelection->fetch());
        } else {
            $this->assertFalse($paymentSelection->fetch());
        }
    }

    private function prepareData(bool $withInvoice): array
    {
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('test@test.sk');

        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $subscriptionTypeRow = $subscriptionTypeBuilder->createNew()
            ->setNameAndUserLabel('test')
            ->setLength(31)
            ->setPrice(1)
            ->setActive(1)
            ->save();

        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->getRepository(PaymentsRepository::class);

        /** @var PaymentsRepository $paymentsRepository */
        $paymentRow = $paymentsRepository->add(
            $subscriptionTypeRow,
            $this->getPaymentGateway(),
            $userRow,
            new PaymentItemContainer(),
            null,
            1,
            null,
            null,
            null,
            0,
            null,
            null,
            null,
            false
        );

        $paymentsRepository->update($paymentRow, ['paid_at' => new DateTime()]);

        if ($withInvoice) {
            /** @var CountriesRepository $countriesRepository */
            $countriesRepository = $this->getRepository(CountriesRepository::class);
            $country = $countriesRepository->findByIsoCode('SK');

            /** @var AddressesRepository $addressesRepository */
            $addressesRepository = $this->getRepository(AddressesRepository::class);
            $addressesRepository->add(
                $userRow,
                'invoice',
                'Test',
                'Test',
                'Test',
                'Test',
                'Test',
                'Test',
                $country->id,
                'Test'
            );

            /** @var InvoiceNumbersRepository $invoiceNumbersRepository */
            $invoiceNumbersRepository = $this->getRepository(InvoiceNumbersRepository::class);
            $invoiceNumbersRepository->insert([
                'delivered_at' => new DateTime(),
                'number' => '100000000',
            ]);
            $invoiceNumberRow = $invoiceNumbersRepository->getTable()
                ->where('number = ?', '100000000')
                ->fetch();

            /** @var InvoicesRepository $invoicesRepository */
            $invoicesRepository = $this->getRepository(InvoicesRepository::class);

            $invoice = $invoicesRepository->add($userRow, $paymentRow, $invoiceNumberRow);

            $paymentsRepository->update($paymentRow, ['invoice_id' => $invoice->id]);
        }

        $paymentSelection = $paymentsRepository->getTable()
            ->where(['payments.id' => $paymentRow->id]);

        return [$paymentSelection, $paymentRow];
    }
}
