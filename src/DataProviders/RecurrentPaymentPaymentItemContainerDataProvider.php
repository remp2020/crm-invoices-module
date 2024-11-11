<?php

namespace Crm\InvoicesModule\DataProviders;

use Crm\InvoicesModule\Models\Vat\VatModeDetector;
use Crm\PaymentsModule\DataProviders\RecurrentPaymentPaymentItemContainerDataProviderInterface;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainerFactory;
use Crm\PaymentsModule\Models\VatRate\VatMode;
use Crm\PaymentsModule\Models\VatRate\VatProcessor;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Nette\Database\Table\ActiveRow;

final class RecurrentPaymentPaymentItemContainerDataProvider implements RecurrentPaymentPaymentItemContainerDataProviderInterface
{

    public function __construct(
        private readonly VatModeDetector $vatModeDetector,
        private readonly VatProcessor $vatProcessor,
        private readonly PaymentItemContainerFactory $paymentItemContainerFactory,
    ) {
    }

    public function createPaymentItemContainer(
        ActiveRow $recurrentPayment,
        ActiveRow $subscriptionType,
    ): ?PaymentItemContainer {
        // continue only if reverse-charge mode is currently applicable to user
        if ($this->vatModeDetector->userVatMode($recurrentPayment->user) !== VatMode::B2BReverseCharge) {
            return null;
        }

        // special creation of PaymentItemContainer is required only for previous reverse-charge payment
        if (!$recurrentPayment->parent_payment ||
            !$this->vatProcessor->isReverseChargePayment($recurrentPayment->parent_payment)) {
            return null;
        }

        $parentPayment = $recurrentPayment->parent_payment;
        $customChargeAmount = $recurrentPayment->custom_amount;

        if ($subscriptionType->id === $parentPayment->subscription_type_id
            && $subscriptionType->id === $recurrentPayment->subscription_type_id
            && !$customChargeAmount
            // DO NOT compare price of subscription type and parent payment amount here - it won't be the same,
            // because of the reverse-charge
        ) {
            $parentPaymentItemContainer = $this->paymentItemContainerFactory->createFromPayment(
                $parentPayment,
                [SubscriptionTypePaymentItem::TYPE]
            );
            // mark container as already reverse-charge applied
            $parentPaymentItemContainer->setPaymentMeta(VatProcessor::PAYMENT_META_VAT_MODE, VatMode::B2BReverseCharge->value);

            $containerToCompare = new PaymentItemContainer();
            $containerToCompare->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));

            // no need to apply reverse-charge to $containerToCompare,
            // since price without VAT should be the same
            if (round($parentPaymentItemContainer->totalPriceWithoutVAT(), 2) ===
                round($containerToCompare->totalPriceWithoutVAT(), 2)
            ) {
                return $parentPaymentItemContainer;
            }
        }

        return null; // otherwise, let the original implementation handle PaymentItemContainer creation
    }
}
