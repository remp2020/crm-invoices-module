<?php

namespace Crm\InvoicesModule\Seeders;

use Crm\ApplicationModule\Builder\ConfigBuilder;
use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Config\Repository\ConfigCategoriesRepository;
use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\Seeders\ConfigsTrait;
use Crm\ApplicationModule\Seeders\ISeeder;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigsSeeder implements ISeeder
{
    use ConfigsTrait;

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
        $category = $this->getCategory($output, 'invoices.config.category', 'fa fa-file-invoice', 200);

        $this->addConfig(
            $output,
            $category,
            'supplier_name',
            ApplicationConfig::TYPE_STRING,
            'invoices.config.supplier_name.name',
            'invoices.config.supplier_name.description',
            null,
            100
        );

        $this->addConfig(
            $output,
            $category,
            'supplier_address',
            ApplicationConfig::TYPE_TEXT,
            'invoices.config.supplier_address.name',
            'invoices.config.supplier_address.description',
            null,
            200
        );

        $this->addConfig(
            $output,
            $category,
            'supplier_city',
            ApplicationConfig::TYPE_TEXT,
            'invoices.config.supplier_city.name',
            'invoices.config.supplier_city.description',
            null,
            200
        );

        $this->addConfig(
            $output,
            $category,
            'supplier_zip',
            ApplicationConfig::TYPE_TEXT,
            'invoices.config.supplier_zip.name',
            'invoices.config.supplier_zip.description',
            null,
            200
        );

        $this->addConfig(
            $output,
            $category,
            'supplier_id',
            ApplicationConfig::TYPE_STRING,
            'invoices.config.supplier_id.name',
            'invoices.config.supplier_id.description',
            null,
            300
        );

        $this->addConfig(
            $output,
            $category,
            'supplier_tax_id',
            ApplicationConfig::TYPE_STRING,
            'invoices.config.supplier_tax_id.name',
            'invoices.config.supplier_tax_id.description',
            null,
            400
        );

        $this->addConfig(
            $output,
            $category,
            'supplier_vat_id',
            ApplicationConfig::TYPE_STRING,
            'invoices.config.supplier_vat_id.name',
            'invoices.config.supplier_vat_id.description',
            null,
            500
        );

        $this->addConfig(
            $output,
            $category,
            'supplier_bank_account_number',
            ApplicationConfig::TYPE_STRING,
            'invoices.config.supplier_bank_account_number.name',
            'invoices.config.supplier_bank_account_number.description',
            null,
            700
        );

        $this->addConfig(
            $output,
            $category,
            'supplier_bank_name',
            ApplicationConfig::TYPE_STRING,
            'invoices.config.supplier_bank_name.name',
            'invoices.config.supplier_bank_name.description',
            null,
            650
        );

        $this->addConfig(
            $output,
            $category,
            'supplier_iban',
            ApplicationConfig::TYPE_STRING,
            'invoices.config.supplier_iban.name',
            'invoices.config.supplier_iban.description',
            null,
            800
        );

        $this->addConfig(
            $output,
            $category,
            'supplier_swift',
            ApplicationConfig::TYPE_STRING,
            'invoices.config.supplier_swift.name',
            'invoices.config.supplier_swift.description',
            null,
            900
        );

        $this->addConfig(
            $output,
            $category,
            'business_register_detail',
            ApplicationConfig::TYPE_STRING,
            'invoices.config.business_register_detail.name',
            'invoices.config.business_register_detail.description',
            null,
            1000
        );

        $this->addConfig(
            $output,
            $category,
            'invoice_constant_symbol',
            ApplicationConfig::TYPE_STRING,
            'invoices.config.invoice_constant_symbol.name',
            'invoices.config.invoice_constant_symbol.description',
            '0308',
            1100
        );

        $this->addConfig(
            $output,
            $category,
            'attach_invoice_to_payment_notification',
            ApplicationConfig::TYPE_BOOLEAN,
            'invoices.config.attach_invoice_to_payment_notification.name',
            null,
            true,
            1200
        );
    }
}
