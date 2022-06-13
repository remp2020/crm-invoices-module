<?php

namespace Crm\InvoicesModule\Hermes;

use Crm\InvoicesModule\InvoiceGenerator;
use Crm\InvoicesModule\Repository\InvoicesRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;
use Tracy\Debugger;

class GenerateInvoiceHandler implements HandlerInterface
{
    private InvoiceGenerator $invoiceGenerator;

    private InvoicesRepository $invoicesRepository;

    private PaymentsRepository $paymentsRepository;

    public function __construct(
        InvoiceGenerator $invoiceGenerator,
        InvoicesRepository $invoicesRepository,
        PaymentsRepository $paymentsRepository
    ) {
        $this->invoiceGenerator = $invoiceGenerator;
        $this->invoicesRepository = $invoicesRepository;
        $this->paymentsRepository = $paymentsRepository;
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if (!isset($payload['payment_id'])) {
            Debugger::log('Unable to generate invoice, event is missing `payment_id`.', Debugger::ERROR);
            return false;
        }
        $payment = $this->paymentsRepository->find($payload['payment_id']);
        if (!$payment) {
            Debugger::log("Unable to find payment to generate invoice for payment_id [{$payload['payment_id']}.", Debugger::ERROR);
            return false;
        }

        // invoice exists, no need to generate it
        if ($payment->invoice_id !== null) {
            return true;
        }

        $user = $payment->user;

        if (!$this->invoicesRepository->isPaymentInvoiceable($payment, false, true)) {
            return false;
        }

        $this->invoiceGenerator->generate($user, $payment);
        return true;
    }
}
