<?php

namespace Crm\InvoicesModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\InvoicesModule\Models\InvoiceNumber\InvoiceNumber;
use Crm\InvoicesModule\Repositories\InvoiceNumbersRepository;
use Crm\PaymentsModule\Models\Gateways\BankTransfer;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\UsersModule\Repositories\UsersRepository;
use DateTime;

class InvoiceNumbersTest extends DatabaseTestCase
{
    /** @var InvoiceNumber */
    protected $invoiceNumber;

    /** @var PaymentsRepository */
    protected $paymentsRepository;

    /** @var PaymentGatewaysRepository */
    protected $paymentGatewaysRepository;

    /** @var UsersRepository */
    protected $usersRepository;

    protected function requiredRepositories(): array
    {
        return [
            InvoiceNumbersRepository::class,
            PaymentsRepository::class,
            UsersRepository::class,
            PaymentGatewaysRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            PaymentGatewaysSeeder::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->invoiceNumber = $this->inject(InvoiceNumber::class);
        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
    }

    public function testThreeWithinMonth()
    {
        $number = $this->invoiceNumber->getNextInvoiceNumber(
            $this->getConfirmedPayment('0000000001', new DateTime('2001-04-15'))
        );
        $this->assertEquals('01m0400001', $number->number);
        $number = $this->invoiceNumber->getNextInvoiceNumber(
            $this->getConfirmedPayment('0000000002', new DateTime('2001-04-15'))
        );
        $this->assertEquals('01m0400002', $number->number);
        $number = $this->invoiceNumber->getNextInvoiceNumber(
            $this->getConfirmedPayment('0000000003', new DateTime('2001-04-16'))
        );
        $this->assertEquals('01m0400003', $number->number);
    }

    public function testThreeMultipleMonths()
    {
        $number = $this->invoiceNumber->getNextInvoiceNumber(
            $this->getConfirmedPayment('0000000001', new DateTime('2001-04-15'))
        );
        $this->assertEquals('01m0400001', $number->number);
        $number = $this->invoiceNumber->getNextInvoiceNumber(
            $this->getConfirmedPayment('0000000002', new DateTime('2001-05-15'))
        );
        $this->assertEquals('01m0500001', $number->number);
        $number = $this->invoiceNumber->getNextInvoiceNumber(
            $this->getConfirmedPayment('0000000003', new DateTime('2001-06-16'))
        );
        $this->assertEquals('01m0600001', $number->number);
    }

    public function testCrossMonths()
    {
        $number = $this->invoiceNumber->getNextInvoiceNumber(
            $this->getConfirmedPayment('0000000001', new DateTime('2001-04-15'))
        );
        $this->assertEquals('01m0400001', $number->number);
        $number = $this->invoiceNumber->getNextInvoiceNumber(
            $this->getConfirmedPayment('0000000002', new DateTime('2001-05-15'))
        );
        $this->assertEquals('01m0500001', $number->number);
        $number = $this->invoiceNumber->getNextInvoiceNumber(
            $this->getConfirmedPayment('0000000003', new DateTime('2001-04-16'))
        );
        $this->assertEquals('01m0400002', $number->number);
    }

    public function testNonstandardOrder()
    {
        $number = $this->invoiceNumber->getNextInvoiceNumber(
            $this->getConfirmedPayment('0000000003', new DateTime('2001-05-16'))
        );
        $this->assertEquals('01m0500001', $number->number);
        $number = $this->invoiceNumber->getNextInvoiceNumber(
            $this->getConfirmedPayment('0000000003', new DateTime('2001-04-16'))
        );
        $this->assertEquals('01m0400001', $number->number);
        $number = $this->invoiceNumber->getNextInvoiceNumber(
            $this->getConfirmedPayment('0000000003', new DateTime('2001-04-15'))
        );
        $this->assertEquals('01m0400002', $number->number);
        $number = $this->invoiceNumber->getNextInvoiceNumber(
            $this->getConfirmedPayment('0000000003', new DateTime('2001-04-14'))
        );
        $this->assertEquals('01m0400003', $number->number);
    }

    protected function getConfirmedPayment($variableSymbol, $paidAt)
    {
        $paymentItemContainer = (new PaymentItemContainer())->addItems([new DonationPaymentItem('donation', 10, 0)]);
        $payment = $this->paymentsRepository->add(null, $this->getPaymentGateway(), $this->getUser(), $paymentItemContainer);
        $this->paymentsRepository->update($payment, [
            'variable_symbol' => $variableSymbol,
            'paid_at' => $paidAt,
        ]);
        $payment = $this->paymentsRepository->updateStatus($payment, PaymentStatusEnum::Paid->value);
        return $payment;
    }

    private $user;

    protected function getUser()
    {
        if (!$this->user) {
            $this->user = $this->usersRepository->add('test@example.com', 'secret');
        }
        return $this->user;
    }

    private $paymentGateway = false;

    protected function getPaymentGateway()
    {
        if (!$this->paymentGateway) {
            $this->paymentGateway = $this->paymentGatewaysRepository->findByCode(BankTransfer::GATEWAY_CODE);
        }
        return $this->paymentGateway;
    }
}
