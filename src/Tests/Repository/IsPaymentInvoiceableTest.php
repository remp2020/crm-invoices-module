<?php

namespace Crm\InvoicesModule\Tests\Repository;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\InvoicesModule\Seeders\AddressTypesSeeder;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentItemMetaRepository;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentMetaRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\AddressTypesRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

/**
 * Testing only method InvoicesRepository->IsPaymentInvoiceable().
 */
class IsPaymentInvoiceableTest extends DatabaseTestCase
{
    private InvoicesRepository $invoicesRepository;
    private PaymentsRepository $paymentsRepository;
    private UsersRepository $usersRepository;

    private ?ActiveRow $paymentGateway = null;
    private ?ActiveRow $user = null;

    protected function requiredRepositories(): array
    {
        return [
            AddressTypesRepository::class,
            AddressesRepository::class,
            CountriesRepository::class,
            InvoicesRepository::class,
            PaymentGatewaysRepository::class,
            PaymentsRepository::class,
            PaymentItemsRepository::class,
            PaymentItemMetaRepository::class,
            PaymentMetaRepository::class,
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

        $this->invoicesRepository = $this->getRepository(InvoicesRepository::class);
        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
    }

    public function testSuccess()
    {
        $user = $this->getUser();
        $this->usersRepository->update($user, ['invoice' => true]);
        $this->usersRepository->update($user, ['disable_auto_invoice' => false]);

        $payment = $this->addPayment(
            $user,
            new DateTime(),
            new DateTime(),
        );

        $isPaymentInvoiceable = $this->invoicesRepository->isPaymentInvoiceable($payment, $ignoreUserInvoice = false);
        $this->assertTrue($isPaymentInvoiceable);
    }

    public function testUserDisabledInvoicing()
    {
        $user = $this->getUser();
        $payment = $this->addPayment(
            $user,
            new DateTime(),
            new DateTime(),
        );

        // user doesn't want invoices
        $this->usersRepository->update($user, ['invoice' => false]);

        $isPaymentInvoiceable = $this->invoicesRepository->isPaymentInvoiceable($payment, $ignoreUserInvoice = false);
        $this->assertFalse($isPaymentInvoiceable);

        // force create invoice ignoring user's setting
        $isPaymentInvoiceable = $this->invoicesRepository->isPaymentInvoiceable($payment, $ignoreUserInvoice = true);
        $this->assertTrue($isPaymentInvoiceable);
    }

    public function testAdminDisabledUsersInvoicing()
    {
        $user = $this->getUser();
        $payment = $this->addPayment(
            $user,
            new DateTime(),
            new DateTime(),
        );

        // admin disabled invoicing for user
        $this->usersRepository->update($user, ['disable_auto_invoice' => true]);

        $isPaymentInvoiceable = $this->invoicesRepository->isPaymentInvoiceable($payment, $ignoreUserInvoice = false);
        $this->assertFalse($isPaymentInvoiceable);
    }

    public function testPaymentNotPaid()
    {
        $user = $this->getUser();
        $this->usersRepository->update($user, ['invoice' => true]);
        $this->usersRepository->update($user, ['disable_auto_invoice' => false]);

        $payment = $this->addPayment(
            $user,
            new DateTime(),
            // paid_at = null
        );

        // check status of payment
        $this->assertEquals('form', $payment->status);

        $isPaymentInvoiceable = $this->invoicesRepository->isPaymentInvoiceable($payment, $ignoreUserInvoice = false);
        $this->assertFalse($isPaymentInvoiceable);
    }


    public function testPaymentPaidWithoutPaidAtDate()
    {
        $user = $this->getUser();
        $this->usersRepository->update($user, ['invoice' => true]);
        $this->usersRepository->update($user, ['disable_auto_invoice' => false]);

        $payment = $this->addPayment(
            $user,
            new DateTime(),
            new DateTime(),
        );

        // set payment to paid, but without paid at datetime
        $this->paymentsRepository->update($payment, ['status' => 'paid', 'paid_at' => null]);
        $this->assertEquals('paid', $payment->status);
        $this->assertNull($payment->paid_at);

        $isPaymentInvoiceable = $this->invoicesRepository->isPaymentInvoiceable($payment, $ignoreUserInvoice = false);
        $this->assertFalse($isPaymentInvoiceable);
    }

    public function testPaymentWithMetaSetToNotInvoiceable()
    {
        $user = $this->getUser();
        $this->usersRepository->update($user, ['invoice' => true]);
        $this->usersRepository->update($user, ['disable_auto_invoice' => false]);

        $payment = $this->addPayment(
            $user,
            new DateTime(),
            new DateTime(),
        );

        /** @var PaymentMetaRepository $paymentMetaRepository */
        $paymentMetaRepository = $this->getRepository(PaymentMetaRepository::class);
        $paymentMetaRepository->add($payment, 'invoiceable', 0);

        $isPaymentInvoiceable = $this->invoicesRepository->isPaymentInvoiceable($payment, $ignoreUserInvoice = false);
        $this->assertFalse($isPaymentInvoiceable);
    }

    public function testPaymentsPaidAtOutOfInvoiceablePeriod()
    {
        $user = $this->getUser();

        $twoMonthsAgo = new DateTime('2 months ago');
        $payment = $this->addPayment(
            $user,
            $twoMonthsAgo,
            $twoMonthsAgo,
        );

        $isPaymentInvoiceable = $this->invoicesRepository->isPaymentInvoiceable($payment);
        $this->assertFalse($isPaymentInvoiceable);
    }

    public function testAddressCheckSuccess()
    {
        $user = $this->getUser();
        $this->usersRepository->update($user, ['invoice' => true]);
        $this->usersRepository->update($user, ['disable_auto_invoice' => false]);

        $payment = $this->addPayment(
            $user,
            new DateTime(),
            new DateTime(),
        );
        $this->addUserAddress('invoice');

        $isPaymentInvoiceable = $this->invoicesRepository->isPaymentInvoiceable($payment, $ignoreUserInvoice = false, $checkUserAddress = true);
        $this->assertTrue($isPaymentInvoiceable);
    }

    public function testAddressCheckMissingAddress()
    {
        $user = $this->getUser();
        $this->usersRepository->update($user, ['invoice' => true]);
        $this->usersRepository->update($user, ['disable_auto_invoice' => false]);

        $payment = $this->addPayment(
            $user,
            new DateTime(),
            new DateTime(),
        );

        $isPaymentInvoiceable = $this->invoicesRepository->isPaymentInvoiceable($payment, $ignoreUserInvoice = false, $checkUserAddress = true);
        $this->assertFalse($isPaymentInvoiceable);
    }

    public function testAddressCheckIncorrectAddressType()
    {
        $payment = $this->addPayment(
            $this->getUser(),
            new DateTime(),
            new DateTime()
        );

        // add address that is not an invoice address type
        /** @var AddressTypesRepository $addressTypesRepository */
        $addressTypesRepository = $this->inject(AddressTypesRepository::class);
        $addressTypesRepository->add('not-an-invoice-type', 'Not an invoice address type');
        $this->addUserAddress('not-an-invoice-type');

        $isPaymentInvoiceable = $this->invoicesRepository->isPaymentInvoiceable($payment, $ignoreUserInvoice = false, $checkUserAddress = true);
        $this->assertFalse($isPaymentInvoiceable);
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
        $payment = $this->paymentsRepository->add(
            null,
            $this->getPaymentGateway(),
            $user,
            new PaymentItemContainer(),
            null,
            1,
            $startSubscriptionAt
        );

        if ($paidAt !== null) {
            $this->paymentsRepository->updateStatus($payment, PaymentsRepository::STATUS_PAID);
            $this->paymentsRepository->update($payment, ['paid_at' => $paidAt]);
        }

        return $this->paymentsRepository->find($payment->id);
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
}
