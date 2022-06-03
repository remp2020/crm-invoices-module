<?php

namespace Crm\InvoicesModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\InvoicesModule\Repository\InvoicesRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\UsersModule\Events\NewAddressEvent;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Tomaj\Hermes\Emitter as HermesEmitter;

class NewAddressHandler extends AbstractListener
{
    private HermesEmitter $hermesEmitter;

    private InvoicesRepository $invoicesRepository;

    private PaymentsRepository $paymentsRepository;

    public function __construct(
        HermesEmitter $hermesEmitter,
        InvoicesRepository $invoicesRepository,
        PaymentsRepository $paymentsRepository
    ) {
        $this->hermesEmitter = $hermesEmitter;
        $this->invoicesRepository = $invoicesRepository;
        $this->paymentsRepository = $paymentsRepository;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof NewAddressEvent)) {
            throw new \Exception('Invalid type of event. Expected: [' . NewAddressEvent::class . ']. Received: [' . get_class($event) . '].');
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
