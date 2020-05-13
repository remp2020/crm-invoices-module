<?php

namespace Crm\InvoicesModule\Events;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\InvoicesModule\InvoiceGenerationException;
use Crm\InvoicesModule\InvoiceGenerator;
use Crm\PaymentsModule\Repository\PaymentsRepository;
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

    public function __construct(
        InvoiceGenerator $invoiceGenerator,
        PaymentsRepository $paymentsRepository,
        ApplicationConfig $applicationConfig
    ) {
        $this->invoiceGenerator = $invoiceGenerator;
        $this->paymentsRepository = $paymentsRepository;
        $this->applicationConfig = $applicationConfig;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof PreNotificationEvent)) {
            throw new \Exception('PreNotificationEvent object expected, instead ' . get_class($event) . ' received');
        }

        $flag = filter_var($this->applicationConfig->get('attach_invoice_to_payment_notification'), FILTER_VALIDATE_BOOLEAN);
        if (!$flag) {
            return false;
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
            } catch (InvoiceGenerationException $e) {
                // Do not cancel notification because invoice couldn't be attached
                Debugger::log('Unable to attach invoice, error: ' . $e->getMessage(), ILogger::ERROR);
            }
        }

        $notificationEvent->setAttachments($attachments);
    }
}
