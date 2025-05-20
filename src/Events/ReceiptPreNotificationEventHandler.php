<?php

namespace Crm\InvoicesModule\Events;

use Crm\InvoicesModule\Models\Generator\ReceiptGenerationException;
use Crm\InvoicesModule\Models\Generator\ReceiptGenerator;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\UsersModule\Events\NotificationContext;
use Crm\UsersModule\Events\PreNotificationEvent;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Tracy\Debugger;
use Tracy\ILogger;

class ReceiptPreNotificationEventHandler extends AbstractListener
{
    private array $enabledNotificationHermesTypes = [];

    public function __construct(
        private ReceiptGenerator $receiptGenerator,
        private PaymentsRepository $paymentsRepository,
    ) {
    }

    /**
     * Receipt will be attached to any NotificationEvent having NotificationContext with given hermes types
     *
     * @param string ...$notificationHermesTypes
     */
    public function enableForNotificationHermesTypes(string ...$notificationHermesTypes): void
    {
        $this->enabledNotificationHermesTypes = $notificationHermesTypes;
    }

    public function handle(EventInterface $event) : void
    {
        if (!($event instanceof PreNotificationEvent)) {
            throw new \Exception('PreNotificationEvent object expected, instead ' . get_class($event) . ' received');
        }

        // Receipt will be attached only in case that correct hermes type is found in NotificationContext
        $notificationContext = $event->getNotificationContext();
        if (!$notificationContext) {
            return;
        }
        $hermesMessageType = $notificationContext->getContextValue(NotificationContext::HERMES_MESSAGE_TYPE);
        if (!$hermesMessageType) {
            return;
        }
        if (!in_array($hermesMessageType, $this->enabledNotificationHermesTypes, true)) {
            return;
        }

        $notificationEvent = $event->getNotificationEvent();
        $params = $notificationEvent->getParams();

        $attachments = $notificationEvent->getAttachments();
        if (isset($params['payment'])) {
            $payment = $this->paymentsRepository->find($params['payment']['id']);

            if (!$payment) {
                throw new ReceiptGenerationException('Unable to find payment with ID [' . $params['payment']['id'] . '].');
            }

            $isReceiptable = $payment->related('payment_meta')
                ->where('key', 'receiptable')
                ->fetch();
            if (!$isReceiptable || !$isReceiptable->value) {
                return;
            }

            try {
                $attachment = $this->receiptGenerator->renderReceiptMailAttachment($payment);
                if ($attachment) {
                    $attachments[] = [
                        'content' => $attachment['content'],
                        'file' => $attachment['file'],
                    ];
                }
            } catch (ReceiptGenerationException $e) {
                Debugger::log('Unable to attach invoice, error: ' . $e->getMessage(), ILogger::ERROR);
            }

            $notificationEvent->setAttachments($attachments);
        }
    }
}
