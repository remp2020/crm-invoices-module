<?php

namespace Crm\InvoicesModule\Events;

use Crm\UsersModule\Events\IAddressEvent;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class AddressRemovedHandler extends AbstractListener
{
    private $usersRepository;

    public function __construct(UsersRepository $usersRepository)
    {
        $this->usersRepository = $usersRepository;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof IAddressEvent)) {
            throw new \Exception("invalid type of event received: " . get_class($event));
        }

        $address = $event->getAddress();
        if (!$address->type === 'invoice') {
            return;
        }

        $this->usersRepository->update($address->user, [
            'invoice' => false,
        ]);
    }
}
