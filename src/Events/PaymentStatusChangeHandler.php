<?php

namespace Crm\InvoicesModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\PaymentsModule\Events\PaymentChangeStatusEvent;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Tomaj\Hermes\Emitter as HermesEmitter;

class PaymentStatusChangeHandler extends AbstractListener
{
    private ApplicationConfig $applicationConfig;

    private HermesEmitter $hermesEmitter;

    public function __construct(
        ApplicationConfig $applicationConfig,
        HermesEmitter $hermesEmitter
    ) {
        $this->applicationConfig = $applicationConfig;
        $this->hermesEmitter = $hermesEmitter;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof PaymentChangeStatusEvent)) {
            throw new \Exception('Invalid type of event. Expected: [' . PaymentChangeStatusEvent::class . ']. Received: [' . get_class($event) . '].');
        }

        $payment = $event->getPayment();

        if ($payment->status !== PaymentStatusEnum::Paid->value) {
            return;
        }

        $flag = filter_var($this->applicationConfig->get('generate_invoice_after_payment'), FILTER_VALIDATE_BOOLEAN);
        if (!$flag) {
            return;
        }

        $this->hermesEmitter->emit(new HermesMessage('generate_invoice', [
            'payment_id' => $payment->id
        ]), HermesMessage::PRIORITY_LOW);
    }
}
