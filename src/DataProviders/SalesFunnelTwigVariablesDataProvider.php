<?php

namespace Crm\InvoicesModule\DataProviders;

use Crm\SalesFunnelModule\DataProviders\SalesFunnelVariablesDataProviderInterface;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Security\User;

class SalesFunnelTwigVariablesDataProvider implements SalesFunnelVariablesDataProviderInterface
{
    public function __construct(
        private readonly User $user,
        private readonly AddressesRepository $addressesRepository,
        private readonly UsersRepository $usersRepository,
    ) {
    }

    public function provide(array $params): array
    {
        $returnParams = [];
        $isLoggedIn = $this->user->isLoggedIn();
        if ($isLoggedIn) {
            $returnParams['invoiceAddress'] = $this->addressesRepository->address($this->usersRepository->find($this->user->getId()), 'invoice');
        }

        return $returnParams;
    }
}
