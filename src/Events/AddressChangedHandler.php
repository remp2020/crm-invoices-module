<?php

namespace Crm\InvoicesModule\Events;

use Crm\UsersModule\Events\AddressChangedEvent;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class AddressChangedHandler extends AbstractListener
{
    private UsersRepository $usersRepository;

    public function __construct(
        UsersRepository $usersRepository
    ) {
        $this->usersRepository = $usersRepository;
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
    }
}
