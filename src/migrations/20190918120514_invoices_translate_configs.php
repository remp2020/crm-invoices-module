<?php

use Phinx\Migration\AbstractMigration;

class InvoicesTranslateConfigs extends AbstractMigration
{
    public function up()
    {
        $this->execute("
            update configs set display_name = 'invoices.config.supplier_name.name' where name = 'supplier_name';
            update configs set description = 'invoices.config.supplier_name.description' where name = 'supplier_name';
            
            update configs set display_name = 'invoices.config.supplier_address.name' where name = 'supplier_address';
            update configs set description = 'invoices.config.supplier_address.description' where name = 'supplier_address';
            
            update configs set display_name = 'invoices.config.supplier_city.name' where name = 'supplier_city';
            update configs set description = 'invoices.config.supplier_city.description' where name = 'supplier_city';
            
            update configs set display_name = 'invoices.config.supplier_zip.name' where name = 'supplier_zip';
            update configs set description = 'invoices.config.supplier_zip.description' where name = 'supplier_zip';
            
            update configs set display_name = 'invoices.config.supplier_id.name' where name = 'supplier_id';
            update configs set description = 'invoices.config.supplier_id.description' where name = 'supplier_id';
            
            update configs set display_name = 'invoices.config.supplier_tax_id.name' where name = 'supplier_tax_id';
            update configs set description = 'invoices.config.supplier_tax_id.description' where name = 'supplier_tax_id';
            
            update configs set display_name = 'invoices.config.supplier_vat_id.name' where name = 'supplier_vat_id';
            update configs set description = 'invoices.config.supplier_vat_id.description' where name = 'supplier_vat_id';
            
            update configs set display_name = 'invoices.config.supplier_bank_account_number.name' where name = 'supplier_bank_account_number';
            update configs set description = 'invoices.config.supplier_bank_account_number.description' where name = 'supplier_bank_account_number';
            
            update configs set display_name = 'invoices.config.supplier_bank_name.name' where name = 'supplier_bank_name';
            update configs set description = 'invoices.config.supplier_bank_name.description' where name = 'supplier_bank_name';
            
            update configs set display_name = 'invoices.config.supplier_iban.name' where name = 'supplier_iban';
            update configs set description = 'invoices.config.supplier_iban.description' where name = 'supplier_iban';
            
            update configs set display_name = 'invoices.config.supplier_swift.name' where name = 'supplier_swift';
            update configs set description = 'invoices.config.supplier_swift.description' where name = 'supplier_swift';
            
            update configs set display_name = 'invoices.config.business_register_detail.name' where name = 'business_register_detail';
            update configs set description = 'invoices.config.business_register_detail.description' where name = 'business_register_detail';
            
            update configs set display_name = 'invoices.config.invoice_constant_symbol.name' where name = 'invoice_constant_symbol';
            update configs set description = 'invoices.config.invoice_constant_symbol.description' where name = 'invoice_constant_symbol';

            update config_categories set name = 'invoices.config.category' where name = 'Faktúry';
            update config_categories set icon = 'fas fa-file-invoice-dollar' where name = 'invoices.config.category';
        ");
    }

    public function down()
    {

    }
}
