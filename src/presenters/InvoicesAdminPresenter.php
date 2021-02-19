<?php

namespace Crm\InvoicesModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\InvoicesModule\InvoiceGenerator;
use Crm\InvoicesModule\Repository\InvoiceNumbersRepository;
use Crm\InvoicesModule\Repository\InvoicesRepository;
use Crm\InvoicesModule\Sandbox\InvoiceSandbox;
use Crm\InvoicesModule\Sandbox\InvoiceZipGenerator;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Application\BadRequestException;
use Nette\Application\Responses\TextResponse;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;
use Tomaj\Hermes\Emitter;

class InvoicesAdminPresenter extends AdminPresenter
{
    /** @var InvoiceGenerator @inject */
    public $invoiceGenerator;

    /** @var  PaymentsRepository @inject */
    public $paymentsRepository;

    /** @var  InvoiceNumbersRepository @inject */
    public $invoiceNumbersRepository;

    /** @var  InvoicesRepository @inject */
    public $invoiceRepository;

    /** @var  InvoiceZipGenerator @inject */
    public $invoiceZipGenerator;

    /** @var InvoiceSandbox @inject */
    public $invoiceSandbox;

    /** @var  Emitter @inject */
    public $hermesEmitter;

    public function actionDownloadInvoice($id)
    {
        $payment = $this->paymentsRepository->find($id);
        if (!$payment) {
            throw new BadRequestException();
        }

        $pdf = null;
        if ($payment->invoice) {
            $pdf = $this->invoiceGenerator->renderInvoicePDF($payment->user, $payment);
        } else {
            $now = new DateTime();
            if ($payment->paid_at->diff($now)->days > InvoiceGenerator::CAN_GENERATE_DAYS_LIMIT) {
                throw new BadRequestException('unable to generate new invoice more than ' . InvoiceGenerator::CAN_GENERATE_DAYS_LIMIT . ' days after the payment');
            }
            if ($payment->user->invoice == true && !$payment->user->disable_auto_invoice) {
                $pdf = $this->invoiceGenerator->generate($payment->user, $payment);
            }
        }

        if (!$pdf) {
            throw new BadRequestException();
        }

        $this->sendResponse($pdf);
        $this->terminate();
    }

    public function actionDownloadNumber($id)
    {
        $payment = $this->findPaymentFromInvoiceNumber($id);

        $pdf = $this->invoiceGenerator->renderInvoicePDF($payment->user, $payment);
        if (!$pdf) {
            throw new BadRequestException();
        }

        $this->sendResponse($pdf);
        $this->terminate();
    }

    private function findPaymentFromInvoiceNumber($invoiceNumber)
    {
        $invoiceNumber = $this->invoiceNumbersRepository->findBy('number', $invoiceNumber);
        if (!$invoiceNumber) {
            $this->sendResponse(new TextResponse('Invoice number not found'));
        }
        $invoice = $this->invoiceRepository->findBy('invoice_number_id', $invoiceNumber->id);
        if (!$invoice) {
            $this->sendResponse(new TextResponse('Invoice not found'));
        }
        $payment = $this->paymentsRepository->findBy('invoice_id', $invoice->id);
        if (!$payment) {
            $this->sendResponse(new TextResponse('Payment not found'));
        }
        return $payment;
    }

    public function renderDefault()
    {
        $this->template->sandboxFiles = $this->invoiceSandbox->getFileList();
    }

    protected function createComponentExportForm()
    {
        $form = new Form();
        $form->setRenderer(new BootstrapInlineRenderer());
        $form->setTranslator($this->translator);

        $form->addText('from_time', 'invoices.admin.export_form.from_time')
            ->setAttribute('class', 'flatpickr');
        $form->addText('to_time', 'invoices.admin.export_form.to_time')
            ->setAttribute('class', 'flatpickr');
        $form->addText('invoices', 'invoices.admin.export_form.invoices');
        $form->addSubmit('submit', 'invoices.admin.export_form.generate');
        $form->setDefaults([
            'from_time' => DateTime::from('-1 month')->format(DATE_RFC3339),
            'to_time' => DateTime::from('now')->format(DATE_RFC3339),
        ]);
        $form->onSuccess[] = function (Form $form, $values) {
            if ($values->invoices) {
                $this->hermesEmitter->emit(new HermesMessage('invoice_zip', [
                    'invoices' => $values['invoices'],
                ]));

                $this->flashMessage($this->translator->translate('invoices.admin.export_form.scheduled'));
                return;
            }

            if ($values->from_time && $values->to_time) {
                $this->hermesEmitter->emit(new HermesMessage('invoice_zip', [
                    'from_time' => $values['from_time'],
                    'to_time' => $values['to_time'],
                ]));

                $this->flashMessage($this->translator->translate('invoices.admin.export_form.scheduled'));
                return;
            }

            $this->redirect('default');
        };
        return $form;
    }

    public function handleDelete($filePath)
    {
        $result = $this->invoiceSandbox->removeFile($filePath);
        if ($result) {
            $this->flashMessage('File was deleted');
        } else {
            $this->flashMessage('Cannot delete file', 'error');
        }
        $this->redirect('default');
    }
}
