<?php

namespace Crm\InvoicesModule\Seeders;

use Crm\ApplicationModule\Builder\ConfigBuilder;
use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Config\Repository\ConfigCategoriesRepository;
use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\Seeders\ISeeder;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigsSeeder implements ISeeder
{
    private $configCategoriesRepository;

    private $configsRepository;

    private $configBuilder;

    public function __construct(
        ConfigCategoriesRepository $configCategoriesRepository,
        ConfigsRepository $configsRepository,
        ConfigBuilder $configBuilder
    ) {
        $this->configCategoriesRepository = $configCategoriesRepository;
        $this->configsRepository = $configsRepository;
        $this->configBuilder = $configBuilder;
    }
    
    public function seed(OutputInterface $output)
    {
        $category = $this->configCategoriesRepository->loadByName('Faktúry');
        if (!$category) {
            $category = $this->configCategoriesRepository->add('Faktúry', 'fa fa-file-invoice', 200);
            $output->writeln('  <comment>* config category <info>Faktúry</info> created</comment>');
        } else {
            $output->writeln(' * config category <info>Faktúry</info> exists');
        }

        $name = 'supplier_name';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('Firma')
                ->setDescription('Meno fakturačnej firmy')
                ->setType(ApplicationConfig::TYPE_STRING)
                ->setAutoload(false)
                ->setConfigCategory($category)
                ->setSorting(100)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }

        $name = 'supplier_address';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('Adresa')
                ->setDescription('Adresa fakturačnej firmy')
                ->setType(ApplicationConfig::TYPE_TEXT)
                ->setAutoload(false)
                ->setConfigCategory($category)
                ->setSorting(200)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }

        $name = 'supplier_city';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('Mesto')
                ->setDescription('Mesto fakturačnej firmy')
                ->setType(ApplicationConfig::TYPE_TEXT)
                ->setAutoload(false)
                ->setConfigCategory($category)
                ->setSorting(200)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }

        $name = 'supplier_zip';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('ZIP')
                ->setDescription('ZIP fakturačnej firmy')
                ->setType(ApplicationConfig::TYPE_TEXT)
                ->setAutoload(false)
                ->setConfigCategory($category)
                ->setSorting(200)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }

        $name = 'supplier_id';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('IČO')
                ->setDescription('Identifikačné číslo fakturačnej firmy')
                ->setType(ApplicationConfig::TYPE_STRING)
                ->setAutoload(false)
                ->setConfigCategory($category)
                ->setSorting(300)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }

        $name = 'supplier_tax_id';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('DIČ')
                ->setDescription('Daňové identifikačné číslo')
                ->setType(ApplicationConfig::TYPE_STRING)
                ->setAutoload(false)
                ->setConfigCategory($category)
                ->setSorting(400)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }

        $name = 'supplier_vat_id';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('IČDPH')
                ->setDescription('Číslo DPH')
                ->setType(ApplicationConfig::TYPE_STRING)
                ->setAutoload(false)
                ->setConfigCategory($category)
                ->setSorting(500)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }

        $name = 'supplier_bank_account_number';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('Bank number')
                ->setDescription('Bank number in old format')
                ->setType(ApplicationConfig::TYPE_STRING)
                ->setAutoload(false)
                ->setConfigCategory($category)
                ->setSorting(700)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }

        $name = 'supplier_bank_name';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('Bank name')
                ->setDescription('Whole bank name, will be displayed on invoice')
                ->setType(ApplicationConfig::TYPE_STRING)
                ->setAutoload(true)
                ->setConfigCategory($category)
                ->setSorting(650)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }

        $name = 'supplier_iban';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('IBAN')
                ->setDescription('Bank number in IBAN format')
                ->setType(ApplicationConfig::TYPE_STRING)
                ->setAutoload(false)
                ->setConfigCategory($category)
                ->setSorting(800)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }

        $name = 'supplier_swift';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('SWIFT')
                ->setDescription('Bank number SWIFT')
                ->setType(ApplicationConfig::TYPE_STRING)
                ->setAutoload(false)
                ->setConfigCategory($category)
                ->setSorting(900)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }

        $name = 'business_register_detail';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('Business register detail')
                ->setDescription("Where was business registered (eg. 'Business register of the District Court Bratislava I., section: sro, id: 4242/DA')")
                ->setType(ApplicationConfig::TYPE_STRING)
                ->setAutoload(true)
                ->setConfigCategory($category)
                ->setSorting(1000)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }

        $name = 'invoice_constant_symbol';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('Constant symbol')
                ->setDescription("Constant symbol (code) used on invoice. If you don't know, leave default '0308'.")
                ->setType(ApplicationConfig::TYPE_STRING)
                ->setValue('0308')
                ->setAutoload(true)
                ->setConfigCategory($category)
                ->setSorting(1100)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }
    }
}
