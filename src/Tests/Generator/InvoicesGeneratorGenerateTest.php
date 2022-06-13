<?php

namespace Crm\InvoicesModule\Tests\Generator;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\InvoicesModule\InvoiceGenerator;
use Crm\InvoicesModule\PaymentNotInvoiceableException;
use Crm\InvoicesModule\Repository\InvoiceItemsRepository;
use Crm\InvoicesModule\Repository\InvoiceNumber;
use Crm\InvoicesModule\Repository\InvoiceNumbersRepository;
use Crm\InvoicesModule\Repository\InvoicesRepository;
use Crm\InvoicesModule\Seeders\AddressTypesSeeder;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\AddressTypesRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use PdfResponse\PdfResponse;

/**
 * Testing only method InvoicesGenerator->generate().
 */
class InvoicesGeneratorGenerateTest extends DatabaseTestCase
{
    private ?ActiveRow $paymentGateway = null;
    private ?ActiveRow $user = null;

    private InvoiceGenerator $invoiceGenerator;

    private InvoiceNumbersRepository $invoiceNumbersRepository;
    private InvoicesRepository $invoicesRepository;
    private PaymentsRepository $paymentsRepository;
    private UsersRepository $usersRepository;

    protected function requiredRepositories(): array
    {
        return [
            AddressesRepository::class,
            AddressTypesRepository::class,
            CountriesRepository::class,
            InvoiceItemsRepository::class,
            InvoiceNumbersRepository::class,
            InvoicesRepository::class,
            PaymentGatewaysRepository::class,
            PaymentItemsRepository::class,
            PaymentsRepository::class,
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

        $this->invoiceGenerator = $this->inject(InvoiceGenerator::class);
        $this->invoiceNumbersRepository = $this->getRepository(InvoiceNumbersRepository::class);
        $this->invoicesRepository = $this->getRepository(InvoicesRepository::class);
        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
    }

    /* *******************************************************************
     * InvoicesGenerator->generator()
     * ***************************************************************** */

    public function testSuccess()
    {
        $user = $this->getUser();
        $payment = $this->addPayment(
            $user,
            new DateTime(),
            new DateTime(),
        );
        $this->addUserAddress('invoice');

        // ensure there are no invoices & invoice numbers
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // *******************************************************************
        // test checks start here

        $response = $this->invoiceGenerator->generate($user, $payment);

        // testing only instance; we don't want to test rendering of template in this test
        $this->assertInstanceOf(PdfResponse::class, $response);

        // check if only one invoice was generated
        $this->assertEquals(1, $this->invoiceNumbersRepository->totalCount());
        $invoices = $this->invoicesRepository->getTable()->fetchAll();
        $this->assertEquals(1, count($invoices));
        $invoice = reset($invoices);

        $this->assertEquals($payment->variable_symbol, $invoice->variable_symbol);

        // fetch payment again; invoice was attached by generator
        $payment = $this->paymentsRepository->find($payment->id);
        $this->assertEquals($invoice->id, $payment->invoice_id);
    }

    public function testSuccessButInvoiceAlreadyExisted()
    {
        $user = $this->getUser();
        $payment = $this->addPayment(
            $user,
            new DateTime(),
            new DateTime(),
        );
        $this->addUserAddress('invoice');

        // add invoice to payment
        /** @var InvoiceNumber $invoiceNumber */
        $invoiceNumber = $this->inject(InvoiceNumber::class);
        $nextInvoiceNumber = $invoiceNumber->getNextInvoiceNumber($payment);
        $invoice = $this->invoicesRepository->add($user, $payment, $nextInvoiceNumber);
        $invoiceLastUpdated = $invoice->updated_date; // keep this for later assert
        $this->paymentsRepository->update($payment, ['invoice_id' => $invoice->id]);

        // ensure there is only this one invoice & invoice number
        $this->assertEquals(1, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(1, $this->invoicesRepository->totalCount());

        // *******************************************************************
        // test checks start here

        $response = $this->invoiceGenerator->generate($user, $payment);

        // testing only instance; we don't want to test rendering of template in this test
        $this->assertInstanceOf(PdfResponse::class, $response);

        // check if no new invoice was generated
        $this->assertEquals(1, $this->invoiceNumbersRepository->totalCount());
        $invoices = $this->invoicesRepository->getTable()->fetchAll();
        $this->assertEquals(1, count($invoices));
        $updatedInvoice = reset($invoices);

        $this->assertEquals($invoice->id, $updatedInvoice->id);
        $this->assertEquals($payment->variable_symbol, $updatedInvoice->variable_symbol);

        // check if link between payment and invoice still exists
        $payment = $this->paymentsRepository->find($payment->id);
        $this->assertEquals($payment->invoice_id, $updatedInvoice->id);

        // and previous invoice not updated
        $this->assertEquals($invoiceLastUpdated, $updatedInvoice->updated_date);
    }

    public function testSuccessTwoPaymentsWithSameVS()
    {
        $user = $this->getUser();
        $this->addUserAddress('invoice');

        // FIRST PAYMENT *************************************************
        $payment1 = $this->addPayment(
            $user,
            new DateTime(),
            new DateTime(),
        );

        // ensure there are no invoices & invoice numbers
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // *******************************************************************
        // (first) test checks start here

        $response = $this->invoiceGenerator->generate($user, $payment1);

        // testing only instance; we don't want to test rendering of template in this test
        $this->assertInstanceOf(PdfResponse::class, $response);

        // check if only one invoice was generated
        $this->assertEquals(1, $this->invoiceNumbersRepository->totalCount());
        $invoices = $this->invoicesRepository->getTable()->fetchAll();
        $this->assertEquals(1, count($invoices));
        $invoice = reset($invoices);


        // fetch payment again; invoice was attached by generator to payment
        $payment1 = $this->paymentsRepository->find($payment1->id);
        $this->assertEquals($payment1->variable_symbol, $invoice->variable_symbol); // expected variable symbol comes from payment
        $this->assertEquals($invoice->id, $payment1->invoice_id); // expected invoice id comes from invoice

        // SECOND PAYMENT (with same variable symbol) ********************
        $payment2 = $this->addPayment(
            $user,
            new DateTime(),
            new DateTime(),
        );
        $this->paymentsRepository->update($payment2, ['variable_symbol' => $payment1->variable_symbol]);
        $payment2 = $this->paymentsRepository->find($payment2->id);

        // ensure there are no invoices & invoice numbers
        $this->assertEquals(1, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(1, $this->invoicesRepository->totalCount());

        // *******************************************************************
        // (second) test checks start here

        $response = $this->invoiceGenerator->generate($user, $payment2);

        // testing only instance; we don't want to test rendering of template in this test
        $this->assertInstanceOf(PdfResponse::class, $response);

        // check if only one (second) invoice was generated
        $this->assertEquals(2, $this->invoiceNumbersRepository->totalCount());
        $invoices = $this->invoicesRepository->getTable()->fetchAll();
        $this->assertEquals(2, count($invoices));
        $invoice1 = reset($invoices);
        $invoice2 = next($invoices);

        // fetch payments again; invoice was attached by generator to payment
        $payment1 = $this->paymentsRepository->find($payment1->id);
        $payment2 = $this->paymentsRepository->find($payment2->id);

        // check if something changed in first invoice
        $this->assertEquals($payment1->variable_symbol, $invoice1->variable_symbol); // expected variable symbol comes from payment
        $this->assertEquals($invoice1->id, $payment1->invoice_id); // expected invoice id comes from invoice
        // and validate second invoice
        $this->assertEquals($payment2->variable_symbol, $invoice2->variable_symbol); // expected variable symbol comes from payment
        $this->assertEquals($invoice2->id, $payment2->invoice_id); // expected invoice id comes from invoice
    }

    public function testUserDisabledInvoicing()
    {
        // prepare "success" conditions
        $user = $this->getUser();
        $payment = $this->addPayment(
            $user,
            new DateTime(),
            new DateTime(),
        );
        $this->addUserAddress('invoice');

        // but disable user invoicing flag
        $this->usersRepository->update($user, [
            'invoice' => false,
        ]);
        $user = $this->usersRepository->find($user->id);

        // ensure there are no invoices & invoice numbers
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // *******************************************************************
        // test checks start here

        // catching & testing exception manually so we can continue with tests
        // (expectExceptionObject would stop processing)
        try {
            $this->invoiceGenerator->generate($user, $payment);
        } catch (\Exception $catchedException) {
            $this->assertInstanceOf(PaymentNotInvoiceableException::class, $catchedException);
            $shouldThrowException = new PaymentNotInvoiceableException($payment->id);
            $this->assertEquals($catchedException->getMessage(), $shouldThrowException->getMessage());
        }

        // no invoice or invoice number generated
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());
    }

    public function testAdminDisabledInvoicing()
    {
        // prepare "success" conditions
        $user = $this->getUser();
        $payment = $this->addPayment(
            $user,
            new DateTime(),
            new DateTime(),
        );
        $this->addUserAddress('invoice');

        // but disable invoicing by admin
        $this->usersRepository->update($user, [
            'disable_auto_invoice' => true,
        ]);
        $user = $this->usersRepository->find($user->id);

        // ensure there are no invoices & invoice numbers
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // *******************************************************************
        // test checks start here

        // catching & testing exception manually so we can continue with tests
        // (expectExceptionObject would stop processing)
        try {
            $this->invoiceGenerator->generate($user, $payment);
        } catch (\Exception $catchedException) {
            $this->assertInstanceOf(PaymentNotInvoiceableException::class, $catchedException);
            $shouldThrowException = new PaymentNotInvoiceableException($payment->id);
            $this->assertEquals($catchedException->getMessage(), $shouldThrowException->getMessage());
        }

        // no invoice or invoice number generated
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());
    }

    public function testPaymentNotPaid()
    {
        $user = $this->getUser();
        $payment = $this->addPayment(
            $user,
            new DateTime(),
        );
        $this->addUserAddress('invoice');

        // ensure there are no invoices & invoice numbers
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // *******************************************************************
        // test checks start here

        // catching & testing exception manually so we can continue with tests
        // (expectExceptionObject would stop processing)
        try {
            $this->invoiceGenerator->generate($user, $payment);
        } catch (\Exception $catchedException) {
            $this->assertInstanceOf(PaymentNotInvoiceableException::class, $catchedException);
            $shouldThrowException = new PaymentNotInvoiceableException($payment->id);
            $this->assertEquals($catchedException->getMessage(), $shouldThrowException->getMessage());
        }

        // no invoice or invoice number generated
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());
    }

    public function testMissingAddress()
    {
        $user = $this->getUser();
        $payment = $this->addPayment(
            $user,
            new DateTime(),
            new DateTime(),
        );

        // ensure there are no invoices & invoice numbers
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // *******************************************************************
        // test checks start here

        // catching & testing exception manually so we can continue with tests
        // (expectExceptionObject would stop processing)
        try {
            $this->invoiceGenerator->generate($user, $payment);
        } catch (\Exception $catchedException) {
            $shouldThrowException = new PaymentNotInvoiceableException($payment->id);
            $this->assertEquals($catchedException->getMessage(), $shouldThrowException->getMessage());
        }

        // no invoice or invoice number generated
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());
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
