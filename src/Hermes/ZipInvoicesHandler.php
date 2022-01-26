<?php

namespace Crm\InvoicesModule\Hermes;

use Crm\InvoicesModule\Repository\InvoiceNumbersRepository;
use Crm\InvoicesModule\Repository\InvoicesRepository;
use Crm\InvoicesModule\Sandbox\InvoiceSandbox;
use Crm\InvoicesModule\Sandbox\InvoiceZipGenerator;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Utils\DateTime;
use Psr\Log\LoggerAwareTrait;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class ZipInvoicesHandler implements HandlerInterface
{
    use LoggerAwareTrait;

    private $invoiceNumbersRepository;

    private $invoicesRepository;

    private $paymentsRepository;

    private $invoiceZipGenerator;

    private $invoiceSandbox;

    public function __construct(
        InvoiceNumbersRepository $invoiceNumbersRepository,
        InvoicesRepository $invoicesRepository,
        PaymentsRepository $paymentsRepository,
        InvoiceZipGenerator $invoiceZipGenerator,
        InvoiceSandbox $invoiceSandbox
    ) {
        $this->invoiceNumbersRepository = $invoiceNumbersRepository;
        $this->invoicesRepository = $invoicesRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->invoiceZipGenerator = $invoiceZipGenerator;
        $this->invoiceSandbox = $invoiceSandbox;
    }

    // todo - refactor, treba niekde dat toto, je to skopirovane z InvoicesAdminPresentera
    private function findPaymentFromInvoiceNumber($invoiceNumber)
    {
        $invoiceNumber = $this->invoiceNumbersRepository->findBy('number', $invoiceNumber);
        if (!$invoiceNumber) {
            return false;
        }
        $invoice = $this->invoicesRepository->findBy('invoice_number_id', $invoiceNumber->id);
        if (!$invoice) {
            return false;
        }
        $payment = $this->paymentsRepository->findBy('invoice_id', $invoice->id);
        if (!$payment) {
            return false;
        }
        return $payment;
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();

        if (isset($payload['invoices']) && $payload['invoices']) {
            $this->logger->info('Generating invoices zip file', ['invoices' => $payload['invoices']]);

            $payments = array_map(function ($number) {
                return $this->findPaymentFromInvoiceNumber(trim($number));
            }, explode(',', $payload['invoices']));

            // Filter out false values
            $payments = array_filter($payments);

            if (count($payments) === 0) {
                $this->logger->info("No invoices with invoice numbers [{$payload['invoices']}] found.");
                return false;
            }

            $zipFile = $this->invoiceZipGenerator->generate($payments);

            $this->invoiceSandbox->addFile($zipFile, 'invoices-' . date('d-m-Y') . '.zip');
            return true;
        }

        if (isset($payload['from_time']) && isset($payload['to_time'])) {
            $from = DateTime::from(strtotime($payload['from_time']));
            $to = DateTime::from(strtotime($payload['to_time']))->setTime(23, 59, 59, 999);

            $payments = $this->paymentsRepository->all()->where([
                'invoice.delivery_date >=' => $from,
                'invoice.delivery_date <=' => $to,
            ])->order('created_at DESC')->fetchAll();

            if (count($payments) === 0) {
                $this->logger->info("No invoices with delivery dates between [{$from}] and [{$to}] found.");
                return false;
            }

            $zipFile = $this->invoiceZipGenerator->generate($payments);

            $filename = 'invoices-' . $from->format('d-m-Y') . '-' . $to->format('d-m-Y')  . '.zip';
            $this->invoiceSandbox->addFile($zipFile, $filename);
            return true;
        }

        return false;
    }
}
