<?php

namespace Crm\InvoicesModule\Events;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\InvoicesModule\InvoiceGenerationException;
use Crm\InvoicesModule\PaymentNotInvoiceableException;
use Crm\InvoicesModule\InvoiceGenerator;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\UsersModule\Events\NotificationContext;
use Crm\UsersModule\Events\PreNotificationEvent;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Tracy\Debugger;
use Tracy\ILogger;

class PreNotificationEventHandler extends AbstractListener
{
    private $invoiceGenerator;

    private $paymentsRepository;

    private $applicationConfig;

    private $enabledNotificationHermesTypes = [];

    public function __construct(
        InvoiceGenerator $invoiceGenerator,
        PaymentsRepository $paymentsRepository,
        ApplicationConfig $applicationConfig
    ) {
        $this->invoiceGenerator = $invoiceGenerator;
        $this->paymentsRepository = $paymentsRepository;
        $this->applicationConfig = $applicationConfig;
    }

    /**
     * Invoice will be attached to any NotificationEvent having NotificationContext with given hermes types
     *
     * @param string ...$notificationHermesTypes
     */
    public function enableForNotificationHermesTypes(string ...$notificationHermesTypes): void
    {
        $this->enabledNotificationHermesTypes = $notificationHermesTypes;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof PreNotificationEvent)) {
            throw new \Exception('PreNotificationEvent object expected, instead ' . get_class($event) . ' received');
        }

        $flag = filter_var($this->applicationConfig->get('attach_invoice_to_payment_notification'), FILTER_VALIDATE_BOOLEAN);
        if (!$flag) {
            return;
        }

        // Invoice will be attached only in case that correct hermes type is found in NotificationContext
        $notificationContext = $event->getNotificationContext();
        if (!$notificationContext) {
            return;
        }
        $hermesMessageType = $notificationContext->getContextValue(NotificationContext::HERMES_MESSAGE_TYPE);
        if (!$hermesMessageType) {
            return;
        }
        if (!in_array($hermesMessageType, $this->enabledNotificationHermesTypes, false)) {
            return;
        }

        $notificationEvent = $event->getNotificationEvent();
        $params = $notificationEvent->getParams();

        // For each payment, check if there is a payment invoice
        // if so, add it to notification
        $attachments = $notificationEvent->getAttachments();
        if (isset($params['payment'])) {
            $payment = $this->paymentsRepository->find($params['payment']['id']);
            try {
                $attachment = $this->invoiceGenerator->renderInvoiceMailAttachment($payment);
                if ($attachment) {
                    $attachments[] =[
                        'content' => $attachment['content'],
                        'file' => $attachment['file'],
                    ];
                }
            } catch (PaymentNotInvoiceableException $e) {
                // Do nothing, no invoice attachment; exception may be raised for valid payments that are not invoiceable
            } catch (InvoiceGenerationException $e) {
                Debugger::log('Unable to attach invoice, error: ' . $e->getMessage(), ILogger::ERROR);
            }
        }

        $notificationEvent->setAttachments($attachments);
    }
}
