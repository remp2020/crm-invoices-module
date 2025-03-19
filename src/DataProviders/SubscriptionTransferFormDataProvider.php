<?php declare(strict_types=1);

namespace Crm\InvoicesModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\InvoicesModule\DataProviders\SubscriptionTransfer\AddressToTransferRetriever;
use Crm\SubscriptionsModule\DataProviders\SubscriptionTransferDataProviderInterface;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Repositories\AddressChangeRequestsRepository;
use Crm\UsersModule\Repositories\AddressesMetaRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\ArrayHash;

class SubscriptionTransferFormDataProvider implements SubscriptionTransferDataProviderInterface
{
    public function __construct(
        private readonly AddressToTransferRetriever $addressToTransferRetriever,
        private readonly AddressChangeRequestsRepository $addressChangeRequestsRepository,
        private readonly AddressesRepository $addressesRepository,
        private readonly AddressesMetaRepository $addressesMetaRepository,
        private readonly SubscriptionsRepository $subscriptionsRepository,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function provide(array $params): void
    {
        if (!isset($params['form'])) {
            throw new DataProviderException('form param missing');
        }
        if (!isset($params['subscription'])) {
            throw new DataProviderException('subscription param missing');
        }

        $form = $params['form'];
        $subscription = $params['subscription'];

        $address = $this->addressToTransferRetriever->retrieve($subscription);
        if ($address !== null) {
            $form->addCheckbox('copy_invoice_address', 'invoices.admin.subscription_transfer.copy_address');
        }
    }

    public function transfer(ActiveRow $subscription, ActiveRow $userToTransferTo, ArrayHash $formData): void
    {
        $address = $this->addressToTransferRetriever->retrieve($subscription);
        if ($address === null) {
            return;
        }

        if (!$formData->copy_invoice_address) {
            return;
        }

        [$copiedAddress, $addressChangeRequest] = $this->copyAddress($userToTransferTo, $address);

        $this->copyAddressMeta($address, $copiedAddress, $addressChangeRequest);

        $this->subscriptionsRepository->update($subscription, [
            'address_id' => $copiedAddress->id,
        ]);
    }

    public function isTransferable(ActiveRow $subscription): bool
    {
        return true;
    }

    /**
     * @return array{ActiveRow, ActiveRow}
     */
    private function copyAddress(ActiveRow $userToTransferTo, ActiveRow $address): array
    {
        $actualAddress = $this->addressesRepository->address($userToTransferTo, 'invoice');

        $addressChangeRequest = $this->addressChangeRequestsRepository->add(
            $userToTransferTo,
            parentAddress: $actualAddress,
            firstName: $address->first_name,
            lastName: $address->last_name,
            companyName: $address->company_name,
            street: $address->street,
            number: $address->number,
            city: $address->city,
            zip: $address->zip,
            countryId: $address->country_id,
            companyId: $address->company_id,
            companyTaxId: $address->company_tax_id,
            companyVatId: $address->company_vat_id,
            phoneNumber: $address->phone_number,
            type: $address->type,
        );

        $existsSameAddress = $addressChangeRequest === false;
        if ($existsSameAddress) {
            $actualAddressChangeRequest = $this->addressChangeRequestsRepository->lastAcceptedForAddress($actualAddress);
            return [$actualAddress, $actualAddressChangeRequest];
        }

        $address = $this->addressChangeRequestsRepository->acceptRequest($addressChangeRequest);

        return [$address, $addressChangeRequest];
    }

    private function copyAddressMeta(ActiveRow $sourceAddress, ActiveRow $copiedAddress, ActiveRow $addressChangeRequest): void
    {
        $sourceAddressChangeRequest = $this->addressChangeRequestsRepository->lastAcceptedForAddress($sourceAddress);
        if ($sourceAddressChangeRequest === null) {
            return;
        }

        $sourceAddressMetas = $sourceAddressChangeRequest->related('addresses_meta')->fetchAll();

        // remove existing metas, if there are any
        $this->addressesMetaRepository->deleteByAddressChangeRequestId($addressChangeRequest->id);

        foreach ($sourceAddressMetas as $sourceAddressMeta) {
            $this->addressesMetaRepository->add(
                $copiedAddress,
                $addressChangeRequest,
                $sourceAddressMeta->key,
                $sourceAddressMeta->value,
            );
        }
    }
}
