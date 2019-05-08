# CRM Invoices Module

Invoices module is an extension to [Payments module](https://github.com/remp2020/crm-payments-module) to provide
invoices for confirmed payments. You can customize the layout of invoices based on the section below.

For invoice to be generated, user needs to provide an invoice address. This is requested on the success
page of the payment and can be changed in the [customer zone](/invoices/invoices/invoice-details) any time.

Invoices are always generated on the fly - system doesn't store generated PDFs for later use. If the template
changes, template of older invoices might change too. Invoice data used for generation are stored at the time
of first generation and never change. Therefore the template can change, but data won't.

Due to the EU legislation, users are allowed to request invoice generation (if it wasn't generated automatically)
only within 15-day period after the payment was made. Other cases need to be handled manually by you and your
accounting team.

## Installing module

We recommend using Composer for installation and update management.

```shell
composer require remp/crm-invoices-module
```

### Enabling module

Add installed extension to your `app/config/config.neon` file.

```neon
extensions:
	- Crm\InvoicesModule\DI\InvoicesModuleExtension
```

### Scheduled commands

Module doesn't provide any commands, that should be scheduled for execution periodically.

### Service commands

Module might provide service commands to be run in the deployed environment. Mostly to handle internal changes
and to prevent direct manipulation with the database. You can display required and optional arguments by using
`--help` switch when running the command.

Invoice module provides:

* `invoice:send`: To manually send an invoice based on payment's variable symbol.

## Invoice templates

Invoice generator uses `.latte` template to generate an invoice PDF. By default the module uses simple and 
generic layout that we prepared. You can see the [source of the layout](./src/model/Generator/templates/invoice/default.latte).

In the example you can see that presenter provides you with `$invoice` variable. Invoice is generated based on
*payment* and reference to *invoice* is stored as `invoice_id` attribute of *payment*. Invoice's reference
back to payment is `variable_symbol`, though it's only indirect and not guaranteed (no foreign keys are present).

Each invoice has a reference to *invoice number* which is a list of unique generated string identifiers guaranteed
to maintain order based on `delivery_date` for the purposes of accounting. You can read the *number* by accessing
`$invoice->invoice_number->number`.

Here's the list of attributes *invoice* provides:

| Name | Value | Nullable | Description |
| --- |---| --- | --- |
| id | *Integer* | no | Internal invoice identifier. |
| invoice_number_id | *Integer* | no | Reference to invoice number. |
| variable_symbol | *String* | no | Public payment identifier. |
| buyer_name | *String* | yes | Name of buyer. Populated from buyer's *invoice* address. |
| buyer_address | *String* | yes | Street of buyer. Populated from buyer's *invoice* address. |
| buyer_zip | *String* | yes | ZIP code of buyer. Populated from buyer's *invoice* address. |
| buyer_city | *String* | yes | City of buyer. Populated from buyer's *invoice* address. |
| buyer_country_id | *Integer* | no | Reference to system country. Populated from buyer's *invoice* address. |
| buyer_id | *String* | yes | Identifier of buyer's company (usually number provided by IRS). Populated from buyer's *invoice* address. |
| buyer_tax_id | *String* | yes | Company's tax identification (usually number provided by IRS). Populated from buyer's *invoice* address. |
| buyer_vat_id | *String* | yes | Company's VAT identification (usually number provided by IRS). Populated from buyer's *invoice* address. |
| supplier_name | *String* | yes | Name of supplier. Populated from application configuration (see `/admin/config-admin`). |
| supplier_address | *String* | yes | Street of supplier. Populated from application configuration (see `/admin/config-admin`). |
| supplier_zip | *String* | yes | ZIP code of supplier. Populated from application configuration (see `/admin/config-admin`). |
| supplier_city | *String* | yes | City of supplier. Populated from application configuration (see `/admin/config-admin`). |
| supplier_country_id | *Integer* | no | Reference to system country. Populated from application configuration (see `/admin/config-admin`). |
| supplier_id | *String* | yes | Identifier of supplier's company (usually number provided by IRS). Populated from application configuration (see `/admin/config-admin`). |
| supplier_tax_id | *String* | yes | Company's tax identification (usually number provided by IRS). Populated from application configuration (see `/admin/config-admin`). |
| supplier_vat_id | *String* | yes | Company's VAT identification (usually number provided by IRS). Populated from application configuration (see `/admin/config-admin`). |
| created_date | *DateTime* | no | Date when invoice was generated. |
| delivery_date | *DateTime* | no | Date when product was delivered to customer. |
| payment_date | *DateTime* | no | Date when payment was confirmed. |

On top of these, application configuration allows you to set some extra values that can be used in invoice template.
You can always create your own config fields within one of your module seeders - see [Config seeder](./src/seeders/ConfigsSeeder.php)
of Invoice module as an example. 

This configuration values are system wide and can be fetched with `$config->get('foo')` - replace `foo` with
one of the following options:

| Name | Value | Description |
| --- |---| --- |
| supplier_bank_account_number | *String* | Your bank number. |
| supplier_bank_name | *String* | Your bank name. |
| supplier_iban | *String* | Your IBAN. |
| supplier_swift | *String* | Your Swift/BIC. |
| contact_email | *String* | Public contact to your company in case of issues. |
| business_register_detail | *String* | Business registration details (i.e. registration file reference) |

Invoice items are fetchable with `$invoice->related('invoice_items')`. Items are generated based on payment items
present at the time of invoice first generation. From that point, items are saved separately. Here's the list
of attributes each *invoice item* provides: 

| Name | Value | Nullable | Description |
| --- |---| --- | --- |
| id | *Integer* | no | Internal invoice item identifier. |
| invoice_id | *Integer* | no | Reference to invoice. |
| text | *String* | no | Item text. Populated from payment item name, might have been extended by extra information (e.g. subscription start/end date). |
| count | *Integer* | no | Number of items sold. |
| price | *Decimal* | no | Unit price of item. |
| vat | *Integer* | no | VAT rate used for sell. |
| currency | *String* | no | Text-based currency (e.g. EUR). |

If you want to use your own layout, prepare your own template with the use of variables described above.
Once it's ready, add following snippet to your `app/config/config.local.neon` file (alter as needed):

```neon
invoiceGenerator:
	setup:
		- setTemplateFile('%appDir%/modules/FooModule/templates/invoices/foo.latte')
``` 

The snippet tells to invoice generator to use template provided at given path instead of default template.