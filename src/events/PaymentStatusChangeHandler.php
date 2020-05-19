<?php

namespace Crm\InvoicesModule\Events;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\PaymentsModule\Events\PaymentChangeStatusEvent;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Tomaj\Hermes\Emitter;

class PaymentStatusChangeHandler extends AbstractListener
{
    private $applicationConfig;

    private $hermesEmitter;

    public function __construct(
        ApplicationConfig $applicationConfig,
        Emitter $hermesEmitter
    ) {
        $this->applicationConfig = $applicationConfig;
        $this->hermesEmitter = $hermesEmitter;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof PaymentChangeStatusEvent)) {
            throw new \Exception('PaymentChangeStatusEvent object expected, instead ' . get_class($event) . ' received');
        }

        $payment = $event->getPayment();

        $flag = filter_var($this->applicationConfig->get('generate_invoice_after_payment'), FILTER_VALIDATE_BOOLEAN);
        if (!$flag) {
            return;
        }

        $this->hermesEmitter->emit(new HermesMessage('generate_invoice', [
            'payment_id' => $payment->id
        ]));
    }
}
