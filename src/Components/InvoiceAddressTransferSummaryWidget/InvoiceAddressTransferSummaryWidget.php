<?php

namespace Crm\InvoicesModule\Components\InvoiceAddressTransferSummaryWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\InvoicesModule\DataProviders\SubscriptionTransfer\AddressToTransferRetriever;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\ViewObjects\Address;
use Exception;

class InvoiceAddressTransferSummaryWidget extends BaseLazyWidget
{
    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        private readonly AddressToTransferRetriever $addressToTransferRetriever,
        private readonly AddressesRepository $addressesRepository,
    ) {
        parent::__construct($lazyWidgetManager);
    }

    public function identifier(): string
    {
        return 'invoiceaddresstransfersummarywidget';
    }

    public function render(array $params): void
    {
        if (!isset($params['subscription'])) {
            throw new Exception("Missing required param 'subscription'.");
        }
        if (!isset($params['userToTransferTo'])) {
            throw new Exception("Missing required param 'userToTransferTo'.");
        }

        $subscription = $params['subscription'];
        $userToTransferTo = $params['userToTransferTo'];

        $address = $this->addressToTransferRetriever->retrieve($subscription);
        if ($address === null) {
            return;
        }

        $actualAddress = $this->addressesRepository->address($userToTransferTo, 'invoice');

        $this->template->addressToCopy = Address::fromActiveRow($address);
        $this->template->targetAccountActualAddress = $actualAddress !== null ? Address::fromActiveRow($actualAddress) : null;

        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . 'invoice_address_transfer_summary_widget.latte');
        $this->template->render();
    }
}
