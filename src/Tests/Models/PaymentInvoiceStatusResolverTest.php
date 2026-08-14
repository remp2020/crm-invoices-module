<?php

namespace Crm\InvoicesModule\Tests\Models;

use Crm\InvoicesModule\Models\Invoice\PaymentInvoiceStatusResolver;
use Crm\InvoicesModule\Models\InvoiceNumber\InvoiceNumber;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\InvoicesModule\Tests\BaseTestCase;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class PaymentInvoiceStatusResolverTest extends BaseTestCase
{
    private PaymentInvoiceStatusResolver $resolver;
    private InvoicesRepository $invoicesRepository;
    private PaymentsRepository $paymentsRepository;
    private UsersRepository $usersRepository;

    private ?ActiveRow $paymentGateway = null;
    private ?ActiveRow $user = null;

    public function setUp(): void
    {
        parent::setUp();

        $this->resolver = $this->inject(PaymentInvoiceStatusResolver::class);
        $this->invoicesRepository = $this->getRepository(InvoicesRepository::class);
        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
    }

    public function testInvoiceExists(): void
    {
        $user = $this->getUser();
        $payment = $this->addPayment($user, new DateTime(), new DateTime());
        $this->addUserAddress('invoice');

        /** @var InvoiceNumber $invoiceNumber */
        $invoiceNumber = $this->inject(InvoiceNumber::class);
        $nextInvoiceNumber = $invoiceNumber->getNextInvoiceNumber($payment);
        $this->paymentsRepository->update($payment, ['invoice_number_id' => $nextInvoiceNumber->id]);
        $invoice = $this->invoicesRepository->add($user, $payment);
        $this->paymentsRepository->update($payment, ['invoice_id' => $invoice->id]);
        $payment = $this->paymentsRepository->find($payment->id);

        $status = $this->resolver->resolve($payment);

        $this->assertFalse($status->isExternal());
        $this->assertTrue($status->invoiceExists);
        $this->assertTrue($status->isDownloadable());
        $this->assertFalse($status->isGeneratable());
        $this->assertNull($status->blockedReasonKey);
    }

    public function testGeneratable(): void
    {
        $user = $this->getUser();
        $payment = $this->addPayment($user, new DateTime(), new DateTime());
        $this->addUserAddress('invoice');

        $status = $this->resolver->resolve($payment);

        $this->assertFalse($status->invoiceExists);
        $this->assertFalse($status->isDownloadable());
        $this->assertTrue($status->isGeneratable());
        $this->assertNull($status->blockedReasonKey);
    }

    public function testInvoiceableButNoAddress(): void
    {
        $user = $this->getUser();
        $payment = $this->addPayment($user, new DateTime(), new DateTime());

        $status = $this->resolver->resolve($payment);

        $this->assertTrue($status->invoiceable);
        $this->assertFalse($status->hasInvoiceAddress);
        $this->assertFalse($status->isGeneratable());
        $this->assertSame(
            'invoices.component.payment_invoice_detail_widget.reason.missing_invoice_address',
            $status->blockedReasonKey,
        );
    }

    public function testPeriodExpired(): void
    {
        $user = $this->getUser();
        $twoMonthsAgo = new DateTime('2 months ago');
        $payment = $this->addPayment($user, $twoMonthsAgo, $twoMonthsAgo);
        $this->addUserAddress('invoice');

        $status = $this->resolver->resolve($payment);

        $this->assertTrue($status->periodExpired);
        $this->assertFalse($status->isGeneratable());
        $this->assertSame('invoices.frontend.invoice_button.not_available_anymore', $status->blockedReasonKey);
    }

    public function testUnpaid(): void
    {
        $user = $this->getUser();
        $payment = $this->addPayment($user, new DateTime());

        $this->assertEquals('form', $payment->status);

        $status = $this->resolver->resolve($payment);

        $this->assertFalse($status->isGeneratable());
        $this->assertSame('invoices.component.payment_invoice_detail_widget.reason.not_paid', $status->blockedReasonKey);
    }

    public function testUserInvoicingDisabled(): void
    {
        $user = $this->getUser();
        $payment = $this->addPayment($user, new DateTime(), new DateTime());
        $this->addUserAddress('invoice');
        $this->usersRepository->update($user, ['invoice' => false]);
        $payment = $this->paymentsRepository->find($payment->id);

        $status = $this->resolver->resolve($payment);

        $this->assertFalse($status->isGeneratable());
        $this->assertSame(
            'invoices.component.payment_invoice_detail_widget.reason.user_invoicing_disabled',
            $status->blockedReasonKey,
        );
    }

    public function testAutoInvoiceDisabled(): void
    {
        $user = $this->getUser();
        $payment = $this->addPayment($user, new DateTime(), new DateTime());
        $this->addUserAddress('invoice');
        $this->usersRepository->update($user, ['disable_auto_invoice' => true]);
        $payment = $this->paymentsRepository->find($payment->id);

        $status = $this->resolver->resolve($payment);

        $this->assertFalse($status->isGeneratable());
        $this->assertSame(
            'invoices.component.payment_invoice_detail_widget.reason.auto_invoice_disabled',
            $status->blockedReasonKey,
        );
    }

    public function testMarkedNotInvoiceable(): void
    {
        $user = $this->getUser();
        $payment = $this->addPayment($user, new DateTime(), new DateTime());
        $this->addUserAddress('invoice');

        /** @var PaymentMetaRepository $paymentMetaRepository */
        $paymentMetaRepository = $this->getRepository(PaymentMetaRepository::class);
        $paymentMetaRepository->add($payment, 'invoiceable', 0);
        $payment = $this->paymentsRepository->find($payment->id);

        $status = $this->resolver->resolve($payment);

        $this->assertFalse($status->isGeneratable());
        $this->assertSame(
            'invoices.component.payment_invoice_detail_widget.reason.marked_not_invoiceable',
            $status->blockedReasonKey,
        );
    }

    public function testCountryMismatch(): void
    {
        $user = $this->getUser();
        $payment = $this->addPayment($user, new DateTime(), new DateTime());
        $this->addUserAddress('invoice'); // country SK

        /** @var CountriesRepository $countriesRepository */
        $countriesRepository = $this->getRepository(CountriesRepository::class);
        $otherCountry = $countriesRepository->findByIsoCode('CZ');
        $this->paymentsRepository->update($payment, ['payment_country_id' => $otherCountry->id]);
        $payment = $this->paymentsRepository->find($payment->id);

        $status = $this->resolver->resolve($payment);

        $this->assertTrue($status->hasInvoiceAddress);
        $this->assertFalse($status->isGeneratable());
        $this->assertSame(
            'invoices.component.payment_invoice_detail_widget.reason.country_mismatch',
            $status->blockedReasonKey,
        );
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
        $payment = $this->paymentsRepository->add(
            null,
            $this->getPaymentGateway(),
            $user,
            new PaymentItemContainer(),
            null,
            1,
            $startSubscriptionAt,
        );

        if ($paidAt !== null) {
            $this->paymentsRepository->updateStatus($payment, PaymentStatusEnum::Paid->value);
            $this->paymentsRepository->update($payment, ['paid_at' => $paidAt]);
        }

        return $this->paymentsRepository->find($payment->id);
    }

    private function addUserAddress(string $addressType): ActiveRow
    {
        /** @var CountriesRepository $countriesRepository */
        $countriesRepository = $this->getRepository(CountriesRepository::class);
        $country = $countriesRepository->findByIsoCode('SK');

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
