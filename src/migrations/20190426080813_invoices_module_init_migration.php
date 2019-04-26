<?php


use Phinx\Migration\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

class InvoicesModuleInitMigration extends AbstractMigration
{
    public function up()
    {
        $sql = <<<SQL
SET NAMES utf8mb4;
SET time_zone = '+00:00';


CREATE TABLE IF NOT EXISTS `invoice_numbers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `delivered_at` datetime NOT NULL,
  `number` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number_id` int(11) NOT NULL,
  `variable_symbol` varchar(255) NOT NULL,
  `buyer_name` varchar(255) DEFAULT NULL,
  `buyer_address` varchar(255) DEFAULT NULL,
  `buyer_zip` varchar(255) DEFAULT NULL,
  `buyer_city` varchar(255) DEFAULT NULL,
  `buyer_country_id` int(11) NOT NULL,
  `buyer_id` varchar(255) DEFAULT NULL,
  `buyer_tax_id` varchar(255) DEFAULT NULL,
  `buyer_vat_id` varchar(255) DEFAULT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `supplier_address` varchar(255) DEFAULT NULL,
  `supplier_zip` varchar(255) DEFAULT NULL,
  `supplier_city` varchar(255) DEFAULT NULL,
  `supplier_id` varchar(255) DEFAULT NULL,
  `supplier_tax_id` varchar(255) DEFAULT NULL,
  `supplier_vat_id` varchar(255) DEFAULT NULL,
  `created_date` datetime NOT NULL,
  `delivery_date` datetime NOT NULL,
  `payment_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_number_id` (`invoice_number_id`),
  KEY `buyer_country_id` (`buyer_country_id`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`invoice_number_id`) REFERENCES `invoice_numbers` (`id`) ON UPDATE NO ACTION,
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`buyer_country_id`) REFERENCES `countries` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `text` varchar(255) NOT NULL,
  `count` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `vat` int(11) NOT NULL,
  `currency` varchar(255) NOT NULL DEFAULT 'EUR',
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SQL;

        $this->execute($sql);

        // add columnt invoice_id to payments table
        if(!$this->table('payments')->hasColumn('invoice_id')) {
            $this->table('payments')
                ->addColumn('invoice_id', 'integer', array('null'=>true))
                ->addForeignKey('invoice_id', 'invoices', 'id', array('delete' => 'RESTRICT', 'update'=> 'NO_ACTION'))
                ->update();
        }
    }

    public function down()
    {
        $this->output->writeln('Down migration is not possible.');
        throw new IrreversibleMigrationException();
    }
}
