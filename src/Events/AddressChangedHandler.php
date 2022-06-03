<?php

namespace Crm\InvoicesModule\Events;

use Crm\UsersModule\Events\IAddressEvent;
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
        if (!($event instanceof IAddressEvent)) {
            throw new \Exception('Invalid type of event. Expected: [' . IAddressEvent::class . ']. Received: [' . get_class($event) . '].');
        }

        $address = $event->getAddress();
        if ($address->type !== 'invoice') {
            return;
        }
        if (!$event->isAdmin()) {
            return;
        }

        $this->usersRepository->update($address->user, [
            'invoice' => true,
            'disable_auto_invoice' => false,
        ]);
    }
}
