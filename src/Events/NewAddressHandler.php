<?php

namespace Crm\InvoicesModule\Events;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\InvoicesModule\Repository\InvoicesRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\UsersModule\Events\NewAddressEvent;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Tomaj\Hermes\Emitter;

class NewAddressHandler extends AbstractListener
{
    private $hermesEmitter;

    private $applicationConfig;

    private $paymentsRepository;

    private $invoicesRepository;

    public function __construct(
        Emitter $hermesEmitter,
        ApplicationConfig $applicationConfig,
        PaymentsRepository $paymentsRepository,
        InvoicesRepository $invoicesRepository
    ) {
        $this->hermesEmitter = $hermesEmitter;
        $this->applicationConfig = $applicationConfig;
        $this->paymentsRepository = $paymentsRepository;
        $this->invoicesRepository = $invoicesRepository;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof NewAddressEvent)) {
            throw new \Exception('NewAddressEvent object expected, instead ' . get_class($event) . ' received');
        }

        $address = $event->getAddress();
        if ($address->type !== 'invoice') {
            return;
        }

        $payments = $this->paymentsRepository->userPayments($address->user_id)
            ->where('invoice_id', null)
            ->fetchAll();

        foreach ($payments as $payment) {
            if ($this->invoicesRepository->isPaymentInvoiceable($payment)) {
                $this->hermesEmitter->emit(new HermesMessage('generate_invoice', [
                    'payment_id' => $payment->id
                ]), HermesMessage::PRIORITY_LOW);
            }
        }
    }
}
