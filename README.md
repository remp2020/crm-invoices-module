# CRM Invoices Module

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

