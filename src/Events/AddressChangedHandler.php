<?php

namespace Crm\InvoicesModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\UsersModule\Events\AddressChangedEvent;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Tomaj\Hermes\Emitter as HermesEmitter;

class AddressChangedHandler extends AbstractListener
{
    public function __construct(
        private HermesEmitter $hermesEmitter,
        private PaymentsRepository $paymentsRepository,
        private UsersRepository $usersRepository
    ) {
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof AddressChangedEvent)) {
            throw new \Exception('Invalid type of event. Expected: [' . AddressChangedEvent::class . ']. Received: [' . get_class($event) . '].');
        }

        $address = $event->getAddress();
        if ($address->type !== 'invoice') {
            return;
        }

        // if invoice address was created by admin, enable invoicing
        if ($event->isAdmin()) {
            $this->usersRepository->update($address->user, [
                'invoice' => true,
                'disable_auto_invoice' => false,
            ]);
        }

        // TODO: Should we check generate_invoice_after_payment config (see PaymentStatusChangeHandler)? If it is disabled, we probably shouldn't generate invoice automatically.

        // TODO: Should we check generate_invoice_number_for_paid_payment also here?
        //       We are generating useless hermes messages if user has disabled invoicing ->
        //       invoice is already generated as hidden by `PaymentStatusChangeHandler`

        $payments = $this->paymentsRepository->userPayments($address->user_id)
            ->where('invoice_id', null)
            ->fetchAll();

        foreach ($payments as $payment) {
            $this->hermesEmitter->emit(new HermesMessage('generate_invoice', [
                'payment_id' => $payment->id
            ]), HermesMessage::PRIORITY_LOW);
        }
    }
}
