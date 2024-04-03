<?php

namespace Crm\InvoicesModule;

use Crm\ApplicationModule\Application\CommandsContainerInterface;
use Crm\ApplicationModule\Application\Managers\SeederManager;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaStorage;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\Event\EventsStorage;
use Crm\ApplicationModule\Models\Event\LazyEventEmitter;
use Crm\ApplicationModule\Models\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Models\Menu\MenuItem;
use Crm\ApplicationModule\Models\User\UserDataRegistrator;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManagerInterface;
use Crm\InvoicesModule\Commands\SendInvoiceCommand;
use Crm\InvoicesModule\Components\DownloadReceiptButton\DownloadReceiptButtonFactory;
use Crm\InvoicesModule\Components\DownloadReceiptButton\ReceiptAdminButtonFactory;
use Crm\InvoicesModule\Components\InvoiceButton\InvoiceAdminButtonFactory;
use Crm\InvoicesModule\Components\InvoiceButton\InvoiceFrontendButtonFactory;
use Crm\InvoicesModule\Components\InvoiceDetailsWidget\InvoiceDetailsWidget;
use Crm\InvoicesModule\Components\InvoiceLabel\InvoiceLabel;
use Crm\InvoicesModule\Components\PaymentSuccessInvoiceWidget\PaymentSuccessInvoiceWidget;
use Crm\InvoicesModule\DataProviders\AddressFormDataProvider;
use Crm\InvoicesModule\DataProviders\ConfigFormDataProvider;
use Crm\InvoicesModule\DataProviders\InvoicesUserDataProvider;
use Crm\InvoicesModule\DataProviders\UserFormDataProvider;
use Crm\InvoicesModule\Events\AddressChangedHandler;
use Crm\InvoicesModule\Events\AddressRemovedHandler;
use Crm\InvoicesModule\Events\NewAddressHandler;
use Crm\InvoicesModule\Events\NewInvoiceEvent;
use Crm\InvoicesModule\Events\PaymentStatusChangeHandler;
use Crm\InvoicesModule\Events\PreNotificationEventHandler;
use Crm\InvoicesModule\Events\ReceiptPreNotificationEventHandler;
use Crm\InvoicesModule\Hermes\GenerateInvoiceHandler;
use Crm\InvoicesModule\Hermes\ZipInvoicesHandler;
use Crm\InvoicesModule\Scenarios\HasInvoiceCriteria;
use Crm\InvoicesModule\Seeders\AddressTypesSeeder;
use Crm\InvoicesModule\Seeders\ConfigsSeeder;
use Crm\InvoicesModule\Seeders\PaymentGatewaysSeeder;
use Crm\PaymentsModule\Events\PaymentChangeStatusEvent;
use Crm\UsersModule\Events\AddressChangedEvent;
use Crm\UsersModule\Events\AddressRemovedEvent;
use Crm\UsersModule\Events\NewAddressEvent;
use Crm\UsersModule\Events\PreNotificationEvent;
use Tomaj\Hermes\Dispatcher;

class InvoicesModule extends CrmModule
{
    public function registerAdminMenuItems(MenuContainerInterface $menuContainer)
    {
        $mainMenu = new MenuItem($this->translator->translate('payments.menu.admin_payments'), '#payments', 'fa fa-file=invoice', 240);

        $menuItem = new MenuItem(
            $this->translator->translate('invoices.admin.menu.export'),
            ':Invoices:InvoicesAdmin:default',
            'fa fa-file-invoice',
            1200
        );
        $menuContainer->attachMenuItemToForeignModule('#payments', $mainMenu, $menuItem);
    }

    public function registerFrontendMenuItems(MenuContainerInterface $menuContainer)
    {
        $menuItem = new MenuItem($this->translator->translate('invoices.menu.invoice_details'), ':Invoices:Invoices:invoiceDetails', '', 550, true);
        $menuContainer->attachMenuItem($menuItem);
    }

    public function registerLazyWidgets(LazyWidgetManagerInterface $widgetManager)
    {
        $widgetManager->registerWidgetFactory(
            'admin.payments.listing.action.menu',
            InvoiceAdminButtonFactory::class,
            400
        );
        $widgetManager->registerWidgetFactory(
            'frontend.payments.listing.receipts',
            InvoiceFrontendButtonFactory::class,
            400
        );
        $widgetManager->registerWidgetFactory(
            'frontend.payments.refund.receipts',
            InvoiceFrontendButtonFactory::class,
            400
        );
        $widgetManager->registerWidgetFactory(
            'frontend.payments.listing.receipts',
            DownloadReceiptButtonFactory::class,
            500
        );

        $widgetManager->registerWidgetFactory(
            'admin.payments.listing.action.menu',
            ReceiptAdminButtonFactory::class,
            500
        );

        $widgetManager->registerWidget(
            'payment.address',
            PaymentSuccessInvoiceWidget::class
        );

        $widgetManager->registerWidget(
            'admin.user.list.emailcolumn',
            InvoiceLabel::class
        );

        $widgetManager->registerWidget(
            'invoices.frontend.invoice_details',
            InvoiceDetailsWidget::class
        );
    }

    public function registerHermesHandlers(Dispatcher $dispatcher)
    {
        $dispatcher->registerHandler(
            'invoice_zip',
            $this->getInstance(ZipInvoicesHandler::class)
        );
        $dispatcher->registerHandler(
            'generate_invoice',
            $this->getInstance(GenerateInvoiceHandler::class)
        );
    }

    public function registerSeeders(SeederManager $seederManager)
    {
        $seederManager->addSeeder($this->getInstance(ConfigsSeeder::class));
        $seederManager->addSeeder($this->getInstance(AddressTypesSeeder::class));
        $seederManager->addSeeder($this->getInstance(PaymentGatewaysSeeder::class));
    }

    public function registerLazyEventHandlers(LazyEventEmitter $emitter)
    {
        $emitter->addListener(
            AddressChangedEvent::class,
            AddressChangedHandler::class
        );
        $emitter->addListener(
            AddressRemovedEvent::class,
            AddressRemovedHandler::class
        );
        $emitter->addListener(
            PreNotificationEvent::class,
            PreNotificationEventHandler::class
        );
        $emitter->addListener(
            PreNotificationEvent::class,
            ReceiptPreNotificationEventHandler::class
        );
        $emitter->addListener(
            NewAddressEvent::class,
            NewAddressHandler::class
        );
        $emitter->addListener(
            PaymentChangeStatusEvent::class,
            PaymentStatusChangeHandler::class
        );
    }

    public function registerScenariosCriteria(ScenariosCriteriaStorage $scenariosCriteriaStorage)
    {
        $scenariosCriteriaStorage->register('payment', HasInvoiceCriteria::KEY, $this->getInstance(HasInvoiceCriteria::class));
    }

    public function registerCommands(CommandsContainerInterface $commandsContainer)
    {
        $commandsContainer->registerCommand($this->getInstance(SendInvoiceCommand::class));
    }

    public function registerDataProviders(DataProviderManager $dataProviderManager)
    {
        $dataProviderManager->registerDataProvider(
            'users.dataprovider.user_form',
            $this->getInstance(UserFormDataProvider::class)
        );

        $dataProviderManager->registerDataProvider(
            'admin.dataprovider.config_form',
            $this->getInstance(ConfigFormDataProvider::class)
        );

        $dataProviderManager->registerDataProvider(
            'users.dataprovider.address_form',
            $this->getInstance(AddressFormDataProvider::class)
        );

        $dataProviderManager->registerDataProvider(
            'invoices.dataprovider.invoice_address_form',
            $this->getInstance(AddressFormDataProvider::class)
        );
    }

    public function registerUserData(UserDataRegistrator $dataRegistrator)
    {
        $dataRegistrator->addUserDataProvider($this->getInstance(InvoicesUserDataProvider::class));
    }

    public function registerEvents(EventsStorage $eventsStorage)
    {
        $eventsStorage->register('new_invoice', NewInvoiceEvent::class, true);
    }
}
