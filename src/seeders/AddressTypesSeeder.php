<?php

namespace Crm\InvoicesModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\UsersModule\Repository\AddressTypesRepository;
use Symfony\Component\Console\Output\OutputInterface;

class AddressTypesSeeder implements ISeeder
{
    private $addressTypesRepository;

    public function __construct(AddressTypesRepository $addressTypesRepository)
    {
        $this->addressTypesRepository = $addressTypesRepository;
    }

    public function seed(OutputInterface $output)
    {
        $types = [
            'invoice' => 'Fakturačná adresa',
        ];

        foreach ($types as $type => $title) {
            if ($this->addressTypesRepository->findBy('type', $type)) {
                $output->writeln("  * address type <info>{$type}</info> exists");
            } else {
                $this->addressTypesRepository->insert([
                    'type' => $type,
                    'title' => $title,
                ]);
                $output->writeln("  <comment>* address type <info>{$type}</info> created</comment>");
            }
        }
    }
}
