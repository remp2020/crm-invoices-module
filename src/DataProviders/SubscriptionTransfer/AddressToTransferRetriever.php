<?php declare(strict_types=1);

namespace Crm\InvoicesModule\DataProviders\SubscriptionTransfer;

use Crm\UsersModule\Repositories\AddressesRepository;
use Nette\Database\Table\ActiveRow;

class AddressToTransferRetriever
{
    public function __construct(
        private readonly AddressesRepository $addressesRepository,
    ) {
    }

    public function retrieve(ActiveRow $subscription): ?ActiveRow
    {
        return $this->addressesRepository->address($subscription->user, 'invoice');
    }
}
