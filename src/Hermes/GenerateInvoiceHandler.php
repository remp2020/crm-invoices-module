<?php

namespace Crm\InvoicesModule\Hermes;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\InvoicesModule\InvoiceGenerator;
use Crm\InvoicesModule\Repository\InvoicesRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Psr\Log\LoggerAwareTrait;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;
use Tracy\Debugger;
use Tracy\ILogger;

class GenerateInvoiceHandler implements HandlerInterface
{
    use LoggerAwareTrait;

    private $invoiceGenerator;

    private $addressesRepository;

    private $applicationConfig;

    private $paymentsRepository;

    private $invoicesRepository;

    public function __construct(
        InvoiceGenerator $invoiceGenerator,
        AddressesRepository $addressesRepository,
        ApplicationConfig $applicationConfig,
        PaymentsRepository $paymentsRepository,
        InvoicesRepository $invoicesRepository
    ) {
        $this->invoiceGenerator = $invoiceGenerator;
        $this->addressesRepository = $addressesRepository;
        $this->applicationConfig = $applicationConfig;
        $this->paymentsRepository = $paymentsRepository;
        $this->invoicesRepository = $invoicesRepository;
    }

    public function handle(MessageInterface $event): bool
    {
        $payload = $event->getPayload();
        $payment = $this->paymentsRepository->find($payload['payment_id']);
        if (!$payment) {
            Debugger::log('Unable to find payment to generate invoice for: ' . $payload['payment_id'], ILogger::ERROR);
            return false;
        }

        $user = $payment->user;

        $address = $this->addressesRepository->address($user, 'invoice');
        if (!$address) {
            return true;
        }

        if (!$this->invoicesRepository->isPaymentInvoiceable($payment)) {
            return true;
        }

        $this->invoiceGenerator->generate($user, $payment);
        return true;
    }
}
