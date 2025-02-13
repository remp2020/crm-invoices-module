<?php

namespace Crm\InvoicesModule\Presenters;

use Contributte\PdfResponse\PdfResponse;
use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\UI\Form;
use Crm\InvoicesModule\Forms\ChangeInvoiceFormFactory;
use Crm\InvoicesModule\Forms\ChangeInvoiceItemsFormFactory;
use Crm\InvoicesModule\Models\Generator\InvoiceGenerator;
use Crm\InvoicesModule\Models\Sandbox\InvoiceSandbox;
use Crm\InvoicesModule\Models\Sandbox\InvoiceZipGenerator;
use Crm\InvoicesModule\Repositories\InvoiceNumbersRepository;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Nette\Application\BadRequestException;
use Nette\Application\Responses\FileResponse;
use Nette\Application\Responses\TextResponse;
use Nette\DI\Attributes\Inject;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;
use Tomaj\Hermes\Emitter;

class InvoicesAdminPresenter extends AdminPresenter
{
    #[Inject]
    public InvoiceGenerator $invoiceGenerator;

    #[Inject]
    public PaymentsRepository $paymentsRepository;

    #[Inject]
    public InvoiceNumbersRepository $invoiceNumbersRepository;

    #[Inject]
    public InvoicesRepository $invoiceRepository;

    #[Inject]
    public InvoiceZipGenerator $invoiceZipGenerator;

    #[Inject]
    public InvoiceSandbox $invoiceSandbox;

    #[Inject]
    public Emitter $hermesEmitter;

    #[Inject]
    public ChangeInvoiceFormFactory $changeInvoiceFormFactory;

    #[Inject]
    public ChangeInvoiceItemsFormFactory $changeInvoiceItemsFormFactory;

    #[Inject]
    public AddressesRepository $addressesRepository;

    /**
     * @admin-access-level read
     */
    public function actionDownloadInvoice($id)
    {
        $payment = $this->paymentsRepository->find($id);
        if (!$payment) {
            throw new BadRequestException();
        }

        $pdf = null;
        if ($payment->invoice) {
            $pdf = $this->invoiceGenerator->renderInvoicePDF($payment->user, $payment);
            $pdf->setSaveMode(PdfResponse::INLINE);
        } elseif ($this->invoiceRepository->isPaymentInvoiceable($payment)) {
            $pdf = $this->invoiceGenerator->generate($payment->user, $payment);
        }

        if (!$pdf) {
            throw new BadRequestException();
        }

        $this->sendResponse($pdf);
    }

    /**
     * @admin-access-level read
     */
    public function actionDownloadNumber($id)
    {
        $payment = $this->findPaymentFromInvoiceNumber($id);

        $pdf = $this->invoiceGenerator->renderInvoicePDF($payment->user, $payment);
        if (!$pdf) {
            throw new BadRequestException();
        }

        $this->sendResponse($pdf);
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

    /**
     * @admin-access-level read
     */
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
            ->setHtmlAttribute('class', 'flatpickr');
        $form->addText('to_time', 'invoices.admin.export_form.to_time')
            ->setHtmlAttribute('class', 'flatpickr');
        $form->addCheckbox('b2b_only', 'invoices.admin.export_form.b2b_only')
            ->setHtmlAttribute('style', 'margin: 0 5px');
        $form->addText('invoices', 'invoices.admin.export_form.invoices');
        $form->addSubmit('submit', 'invoices.admin.export_form.generate');
        $form->setDefaults([
            'from_time' => DateTime::from('-1 month')->format(DATE_RFC3339),
            'to_time' => DateTime::from('now')->format(DATE_RFC3339),
            'b2b_only' => true,
        ]);
        $form->onSuccess[] = function (Form $form, $values) {
            if ($values->invoices) {
                $this->hermesEmitter->emit(new HermesMessage('invoice_zip', [
                    'invoices' => $values['invoices'],
                ]), HermesMessage::PRIORITY_LOW);

                $this->flashMessage($this->translator->translate('invoices.admin.export_form.scheduled'));
                return;
            }

            if ($values->from_time && $values->to_time) {
                $this->hermesEmitter->emit(new HermesMessage('invoice_zip', [
                    'from_time' => $values['from_time'],
                    'to_time' => $values['to_time'],
                    'b2b_only' => $values['b2b_only'],
                ]), HermesMessage::PRIORITY_LOW);

                $this->flashMessage($this->translator->translate('invoices.admin.export_form.scheduled'));
                return;
            }

            $this->redirect('default');
        };
        return $form;
    }

    /**
     * @admin-access-level read
     */
    public function handleDownloadExport($fileName)
    {
        $response = new FileResponse($this->invoiceSandbox->getFilePath($fileName));
        $response->resuming = false;
        $this->sendResponse($response);
    }

    /**
     * @admin-access-level write
     */
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

    /**
     * @admin-access-level write
     */
    public function renderEdit($id)
    {
        $invoice = $this->invoiceRepository->find($id);
        if (!$invoice) {
            throw new BadRequestException('Invalid invoice ID provided: ' . $this->getParameter('id'));
        }

        $payment = $invoice->related('payments')->fetch();
        if (!$payment) {
            throw new BadRequestException("Invoice {$this->getParameter('id')} is not related to any payment");
        }

        $pdf = $this->invoiceGenerator->renderInvoicePDF($payment->user, $payment);

        $this->template->pdf = $pdf;
        $this->template->paymentId = $payment->id;
        $this->template->userRow = $payment->user;
        $this->template->invoice = $invoice;
    }

    public function createComponentChangeInvoiceForm()
    {
        $id = $this->getParameter('id');
        $form = $this->changeInvoiceFormFactory->create($id);

        $invoice = $this->invoiceRepository->find($id);
        if ($invoice) {
            $defaults = [
                'buyer_name' => $invoice->buyer_name,
                'buyer_address' => $invoice->buyer_address,
                'buyer_city' => $invoice->buyer_city,
                'buyer_zip' => $invoice->buyer_zip,
                'country_id' => $invoice->buyer_country_id,
                'company_id' => $invoice->buyer_id,
                'company_tax_id' => $invoice->buyer_tax_id,
                'company_vat_id' => $invoice->buyer_vat_id
            ];
            $form->setDefaults($defaults);
        }

        $this->changeInvoiceFormFactory->onSuccess = function () {
            $this->flashMessage($this->translator->translate('invoices.admin.edit.success'));
        };

        $form->onError[] = function ($form) {
            $this->flashMessage(implode('', $form->getErrors()), 'error');
        };
        return $form;
    }

    public function createComponentCurrentInvoiceDetailsForm()
    {
        $id = $this->getParameter('id');
        $form = $this->changeInvoiceFormFactory->create($id);

        $invoice = $this->invoiceRepository->find($id);
        if ($invoice) {
            $payment = $invoice->related('payments')->fetch();
            if ($payment) {
                $address = $this->addressesRepository->address($payment->user, 'invoice');
                if ($address) {
                    $defaults = [
                        'buyer_name' => $address->company_name,
                        'buyer_address' => $address->address . ' ' . $address->number,
                        'buyer_city' => $address->city,
                        'buyer_zip' => $address->zip,
                        'country_id' => $address->country_id,
                        'company_id' => $address->company_id,
                        'company_tax_id' => $address->company_tax_id,
                        'company_vat_id' => $address->company_vat_id
                    ];
                    $form->setDefaults($defaults);
                }
            }
        }

        $this->changeInvoiceFormFactory->onSuccess = function () {
            $this->flashMessage($this->translator->translate('invoices.admin.edit.success'));
        };
        $form->onError[] = function ($form) {
            $this->flashMessage(implode('', $form->getErrors()), 'error');
        };
        return $form;
    }

    public function createComponentInvoiceItemsForm()
    {
        $id = $this->getParameter('id');
        $form = $this->changeInvoiceItemsFormFactory->create($id);

        $this->changeInvoiceItemsFormFactory->onSuccess = function () {
            $this->flashMessage($this->translator->translate('invoices.admin.edit.success'));
        };
        $form->onError[] = function ($form) {
            $this->flashMessage(implode('', $form->getErrors()), 'error');
        };
        return $form;
    }
}
