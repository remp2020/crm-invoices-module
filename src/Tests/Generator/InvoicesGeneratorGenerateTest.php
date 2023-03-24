<?php

namespace Crm\InvoicesModule\Tests\Generator;

use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\InvoicesModule\InvoiceGenerator;
use Crm\InvoicesModule\PaymentNotInvoiceableException;
use Crm\InvoicesModule\Repository\InvoiceItemsRepository;
use Crm\InvoicesModule\Repository\InvoiceNumber;
use Crm\InvoicesModule\Repository\InvoiceNumbersRepository;
use Crm\InvoicesModule\Repository\InvoicesRepository;
use Crm\InvoicesModule\Seeders\AddressTypesSeeder;
use Crm\InvoicesModule\Seeders\ConfigsSeeder;
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

    private ConfigsRepository $configsRepository;
    private InvoiceNumbersRepository $invoiceNumbersRepository;
    private InvoicesRepository $invoicesRepository;
    private PaymentsRepository $paymentsRepository;
    private UsersRepository $usersRepository;

    protected function requiredRepositories(): array
    {
        return [
            AddressesRepository::class,
            AddressTypesRepository::class,
            ConfigsRepository::class,
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
            ConfigsSeeder::class, // supplier/invoicing configs
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

        $this->configsRepository = $this->getRepository(ConfigsRepository::class);
        $supplierConfig = $this->configsRepository->findBy('name', 'supplier_name');
        $this->configsRepository->update($supplierConfig, ['value' => 'Test invoice supplier']);
        $supplierConfig = $this->configsRepository->findBy('name', 'supplier_address');
        $this->configsRepository->update($supplierConfig, ['value' => 'Test avenue']);
        $supplierConfig = $this->configsRepository->findBy('name', 'supplier_city');
        $this->configsRepository->update($supplierConfig, ['value' => 'Invoiceville']);

        // ensure before tests that hidden invoice is disabled (it's default, but we want to be sure)
        $generateInvoiceNumberForPaidPayment = $this->configsRepository->findBy('name', 'generate_invoice_number_for_paid_payment');
        $this->assertEquals(0, $generateInvoiceNumberForPaidPayment->value);
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
        $this->paymentsRepository->update($payment, ['invoice_number_id' => $nextInvoiceNumber->id]);
        $invoice = $this->invoicesRepository->add($user, $payment);
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

        // *******************************************************************
        // USER INVOICE FLAG DISABLED
        $this->usersRepository->update($user, [
            'invoice' => false,
        ]);
        $user = $this->usersRepository->find($user->id);

        // ensure there are no invoices & invoice numbers
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // *******************************************************************
        // (invoice=0) test checks start here

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

        // *******************************************************************
        // USER INVOICE FLAG ENABLED
        $this->usersRepository->update($user, [
            'invoice' => true,
        ]);
        $user = $this->usersRepository->find($user->id);

        // ensure there are no invoices & invoice number
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // *******************************************************************
        // (invoice=1) test checks start here

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

        // ADMIN DISABLE INVOICE FLAG ENABLED
        $this->usersRepository->update($user, [
            'disable_auto_invoice' => true,
        ]);
        $user = $this->usersRepository->find($user->id);

        // ensure there are no invoices & invoice numbers
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // *******************************************************************
        // (disable_auto_invoice=1) test checks start here

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

        // *******************************************************************
        // ADMIN DISABLE INVOICE FLAG DISABLED
        $this->usersRepository->update($user, [
            'disable_auto_invoice' => false,
        ]);
        $user = $this->usersRepository->find($user->id);

        // ensure there are no invoices & invoice number
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // *******************************************************************
        // (disable_auto_invoice=0) test checks start here

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
        // (no address) test checks start here

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

        // *******************************************************************
        // ADD ADDRESS LATER *************************************************
        $address = $this->addUserAddress('invoice');

        // *******************************************************************
        // (address added) test checks start here

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

        // and address on invoice is not empty
        $this->assertEquals($address->first_name . ' ' . $address->last_name, $invoice->buyer_name);
        $this->assertEquals($address->address . ' ' . $address->number, $invoice->buyer_address);
        $this->assertEquals($address->city, $invoice->buyer_city);
        $this->assertEquals($address->zip, $invoice->buyer_zip);
        $this->assertEquals($address->country_id, $invoice->buyer_country_id);
    }

    /* *******************************************************************
     * Same tests but with enabled config `generate_invoice_number_for_paid_payment`.
     * ***************************************************************** */

    private function enableGenerateInvoiceHiddenIfAddressMissingConfig()
    {
        // enable config
        $generateInvoiceNumberForPaidPayment = $this->configsRepository->findBy('name', 'generate_invoice_number_for_paid_payment');
        $this->configsRepository->update($generateInvoiceNumberForPaidPayment, ['value' => true]);
    }

    public function testWithHiddenInvoiceNumberEnabledSuccess()
    {
        $this->enableGenerateInvoiceHiddenIfAddressMissingConfig();
        // no change in result of test
        $this->testSuccess();
    }

    public function testWithHiddenInvoiceNumberEnabledSuccessButInvoiceAlreadyExisted()
    {
        $this->enableGenerateInvoiceHiddenIfAddressMissingConfig();
        // no change in result of test
        $this->testSuccessButInvoiceAlreadyExisted();
    }

    public function testWithHiddenInvoiceNumberEnabledSuccessTwoPaymentsWithSameVS()
    {
        $this->enableGenerateInvoiceHiddenIfAddressMissingConfig();
        // no change in result of test
        $this->testSuccessTwoPaymentsWithSameVS();
    }

    public function testWithHiddenInvoiceNumberEnabledUserDisabledInvoicing()
    {
        $this->enableGenerateInvoiceHiddenIfAddressMissingConfig();
        // test changed; with config enabled, we ignore user `invoice` settings (and hidden invoice number is generated)
        // $this->testUserDisabledInvoicing();

        // prepare "success" conditions
        $user = $this->getUser();
        $payment = $this->addPayment(
            $user,
            new DateTime(),
            new DateTime(),
        );
        $address = $this->addUserAddress('invoice');

        // *******************************************************************
        // USER INVOICE FLAG DISABLED
        $this->usersRepository->update($user, [
            'invoice' => false,
        ]);
        $user = $this->usersRepository->find($user->id);

        // ensure there are no invoices & invoice numbers
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // *******************************************************************
        // (invoice=0) test checks start here

        // catching & testing exception manually so we can continue with tests
        // (expectExceptionObject would stop processing)
        try {
            $this->invoiceGenerator->generate($user, $payment);
        } catch (\Exception $catchedException) {
            $this->assertInstanceOf(PaymentNotInvoiceableException::class, $catchedException);
            $shouldThrow = new PaymentNotInvoiceableException($payment->id);
            $this->assertEquals($catchedException->getMessage(), $shouldThrow->getMessage());
        }

        // invoice number was generated but no invoice
        $this->assertEquals(1, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // invoice number is linked to payment
        $firstInvoiceNumber = $this->invoiceNumbersRepository->getTable()->fetch();
        $payment = $this->paymentsRepository->find($payment->id); // refresh data
        $this->assertEquals($payment->invoice_number_id, $firstInvoiceNumber->id);
        // not used by any invoice
        $this->assertNull($this->invoicesRepository->findBy('invoice_number_id', $firstInvoiceNumber->id));

        // *******************************************************************
        // USER INVOICE FLAG ENABLED
        $this->usersRepository->update($user, [
            'invoice' => true,
        ]);
        $user = $this->usersRepository->find($user->id);

        // ensure there are no invoices & and only one invoice number
        $this->assertEquals(1, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // *******************************************************************
        // (invoice=1) test checks start here

        $result = $this->invoiceGenerator->generate($user, $payment);
        $this->assertNotNull($result); // invoice was generated & returned

        // refresh payment data
        $payment = $this->paymentsRepository->find($payment->id);

        // no new invoice number was generated
        $invoiceNumbers = $this->invoiceNumbersRepository->getTable()->fetchAll();
        $this->assertEquals(1, count($invoiceNumbers));
        $updatedInvoiceNumber = reset($invoiceNumbers);
        // just to be sure, check if number wasn't changed (eg. update; or removal & newly generated)
        $this->assertEquals($firstInvoiceNumber->number, $updatedInvoiceNumber->number);
        $this->assertEquals($firstInvoiceNumber->id, $updatedInvoiceNumber->id);

        // invoice was generated
        $invoices = $this->invoicesRepository->getTable()->fetchAll();
        $this->assertEquals(1, count($invoices));
        $invoice = reset($invoices);
        $this->assertEquals($firstInvoiceNumber->number, $invoice->invoice_number->number);

        // fetch payment again; invoice now should be attached by generator
        // (because address is not missing and invoice is not hidden)
        $payment = $this->paymentsRepository->find($payment->id);
        $this->assertEquals($invoice->id, $payment->invoice_id);
        $this->assertEquals($payment->variable_symbol, $invoice->variable_symbol);
        $this->assertEquals($payment->invoice_number_id, $updatedInvoiceNumber->id);
    }

    public function testWithHiddenInvoiceNumberEnabledAdminDisabledInvoicing()
    {
        $this->enableGenerateInvoiceHiddenIfAddressMissingConfig();
        // test changed; with config enabled, we ignore admin `disable_auto_invoice` setting (and hidden invoice number is generated)
        // $this->testAdminDisabledInvoicing();

        // prepare "success" conditions
        $user = $this->getUser();
        $payment = $this->addPayment(
            $user,
            new DateTime(),
            new DateTime(),
        );
        $this->addUserAddress('invoice');

        // ADMIN DISABLE INVOICE FLAG ENABLED
        $this->usersRepository->update($user, [
            'disable_auto_invoice' => true,
        ]);
        $user = $this->usersRepository->find($user->id);

        // ensure there are no invoices & invoice numbers
        $this->assertEquals(0, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // *******************************************************************
        // (disable_auto_invoice=1) test checks start here

        // catching & testing exception manually so we can continue with tests
        // (expectExceptionObject would stop processing)
        try {
            $this->invoiceGenerator->generate($user, $payment);
        } catch (\Exception $catchedException) {
            $this->assertInstanceOf(PaymentNotInvoiceableException::class, $catchedException);
            $shouldThrow = new PaymentNotInvoiceableException($payment->id);
            $this->assertEquals($catchedException->getMessage(), $shouldThrow->getMessage());
        }

        // invoice number was generated but no invoice
        $this->assertEquals(1, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // invoice number is linked to payment
        $firstInvoiceNumber = $this->invoiceNumbersRepository->getTable()->fetch();
        $payment = $this->paymentsRepository->find($payment->id); // refresh data
        $this->assertEquals($payment->invoice_number_id, $firstInvoiceNumber->id);
        // not used by any invoice
        $this->assertNull($this->invoicesRepository->findBy('invoice_number_id', $firstInvoiceNumber->id));

        // *******************************************************************
        // ADMIN DISABLE INVOICE FLAG DISABLED
        $this->usersRepository->update($user, [
            'disable_auto_invoice' => false,
        ]);
        $user = $this->usersRepository->find($user->id);

        // ensure there are no invoices & and only one invoice number
        $this->assertEquals(1, $this->invoiceNumbersRepository->totalCount());
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // *******************************************************************
        // (disable_auto_invoice=0) test checks start here

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

    public function testWithHiddenInvoiceNumberEnabledPaymentNotPaid()
    {
        $this->enableGenerateInvoiceHiddenIfAddressMissingConfig();
        // no change in result of test
        $this->testPaymentNotPaid();
    }

    public function testWithHiddenInvoiceNumberEnabledMissingAddress()
    {
        $this->enableGenerateInvoiceHiddenIfAddressMissingConfig();
        // test changed; with config enabled, we ignore missing address (and hidden invoice number is generated)
        // $this->testMissingAddress();

        // *******************************************************************
        // NO ADDRESS ****************************************************
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
        // (no address) test checks start here

        // catching & testing exception manually so we can continue with tests
        // (expectExceptionObject would stop processing)
        try {
            $this->invoiceGenerator->generate($user, $payment);
        } catch (\Exception $catchedException) {
            $this->assertInstanceOf(PaymentNotInvoiceableException::class, $catchedException);
            $shouldThrow = new PaymentNotInvoiceableException($payment->id);
            $this->assertEquals($catchedException->getMessage(), $shouldThrow->getMessage());
        }

        // refresh payment data
        $payment = $this->paymentsRepository->find($payment->id);

        // no invoice was generated
        $this->assertEquals(0, $this->invoicesRepository->totalCount());

        // but there is one invoice number linked to payment
        $invoiceNumbers = $this->invoiceNumbersRepository->getTable()->fetchAll();
        $this->assertEquals(1, count($invoiceNumbers));
        $firstInvoiceNumber = reset($invoiceNumbers);
        $this->assertEquals($payment->invoice_number_id, $firstInvoiceNumber->id);
        // not used by any invoice
        $this->assertNull($this->invoicesRepository->findBy('invoice_number_id', $firstInvoiceNumber->id));

        // *******************************************************************
        // ADD ADDRESS LATER *************************************************
        $address = $this->addUserAddress('invoice');

        // *******************************************************************
        // (address added) test checks start here

        $result = $this->invoiceGenerator->generate($user, $payment); // this would be triggered by handler AddressChangedHandler after address was added
        $this->assertNotNull($result); // invoice was generated & returned

        // no new invoice number was generated
        $invoiceNumbers = $this->invoiceNumbersRepository->getTable()->fetchAll();
        $this->assertEquals(1, count($invoiceNumbers));
        $updatedInvoiceNumber = reset($invoiceNumbers);
        // just to be sure, check if number wasn't changed (eg. update; or removal & newly generated)
        $this->assertEquals($firstInvoiceNumber->number, $updatedInvoiceNumber->number);
        $this->assertEquals($firstInvoiceNumber->id, $updatedInvoiceNumber->id);

        // invoice was generated
        $invoices = $this->invoicesRepository->getTable()->fetchAll();
        $this->assertEquals(1, count($invoices));
        $invoice = reset($invoices);
        $this->assertEquals($firstInvoiceNumber->number, $invoice->invoice_number->number);

        // fetch payment again; invoice now should be attached by generator
        // (because address is not missing and invoice is not hidden)
        $payment = $this->paymentsRepository->find($payment->id);
        $this->assertEquals($invoice->id, $payment->invoice_id);
        $this->assertEquals($payment->variable_symbol, $invoice->variable_symbol);
        $this->assertEquals($payment->invoice_number_id, $updatedInvoiceNumber->id);

        // and address on invoice is not empty
        $this->assertEquals($address->first_name . ' ' . $address->last_name, $invoice->buyer_name);
        $this->assertEquals($address->address . ' ' . $address->number, $invoice->buyer_address);
        $this->assertEquals($address->city, $invoice->buyer_city);
        $this->assertEquals($address->zip, $invoice->buyer_zip);
        $this->assertEquals($address->country_id, $invoice->buyer_country_id);
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
