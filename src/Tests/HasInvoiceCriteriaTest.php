<?php

namespace Crm\InvoicesModule\Tests;

use Crm\ApplicationModule\Seeders\CountriesSeeder;
use Crm\InvoicesModule\Repositories\InvoiceNumbersRepository;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\InvoicesModule\Scenarios\HasInvoiceCriteria;
use Crm\InvoicesModule\Seeders\AddressTypesSeeder;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Tests\PaymentsTestCase;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\AddressTypesRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Nette\Utils\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;

class HasInvoiceCriteriaTest extends PaymentsTestCase
{
    public function requiredRepositories(): array
    {
        return array_merge(parent::requiredRepositories(), [
            InvoicesRepository::class,
            InvoiceNumbersRepository::class,
            AddressesRepository::class,
            CountriesRepository::class,
            AddressTypesRepository::class,
        ]);
    }

    public function requiredSeeders(): array
    {
        return array_merge(parent::requiredSeeders(), [
            CountriesSeeder::class,
            AddressTypesSeeder::class,
        ]);
    }

    public static function dataProviderForTestHasInvoiceCriteria(): array
    {
        return [
            [1, true, true],
            [0, true, false],
            [1, false, false],
            [0, false, true],
        ];
    }

    /**
     * @group unreliable
     */
    #[DataProvider('dataProviderForTestHasInvoiceCriteria')]
    public function testHasInvoiceCriteria($hasInvoice, $shoudHaveInvoice, $expectedResult)
    {
        [$paymentSelection, $paymentRow] = $this->prepareData($hasInvoice);

        $criteria = $this->inject(HasInvoiceCriteria::class);
        $values = (object)['selection' => $shoudHaveInvoice];
        $criteria->addConditions($paymentSelection, [HasInvoiceCriteria::KEY => $values], $paymentRow);

        if ($expectedResult) {
            $this->assertNotNull($paymentSelection->fetch());
        } else {
            $this->assertNull($paymentSelection->fetch());
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
            $this->paymentsRepository->update($paymentRow, ['invoice_number_id' => $invoiceNumberRow->id]);

            /** @var InvoicesRepository $invoicesRepository */
            $invoicesRepository = $this->getRepository(InvoicesRepository::class);

            $invoice = $invoicesRepository->add($userRow, $paymentRow);

            $paymentsRepository->update($paymentRow, ['invoice_id' => $invoice->id]);
        }

        $paymentSelection = $paymentsRepository->getTable()
            ->where(['payments.id' => $paymentRow->id]);

        return [$paymentSelection, $paymentRow];
    }
}
