<?php

namespace Crm\InvoicesModule\Forms;

use Crm\ApplicationModule\UI\Form;
use Crm\InvoicesModule\Repositories\InvoiceItemsRepository;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Nette\Localization\Translator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class ChangeInvoiceItemsFormFactory
{
    public $onSuccess;

    private $translator;

    private $invoicesRepository;


    private $invoiceItemsRepository;

    public function __construct(
        Translator $translator,
        InvoicesRepository $invoicesRepository,
        InvoiceItemsRepository $invoiceItemsRepository,
    ) {
        $this->translator = $translator;
        $this->invoicesRepository = $invoicesRepository;
        $this->invoiceItemsRepository = $invoiceItemsRepository;
    }

    /**
     * @param int $id
     * @return Form
     */
    public function create(int $id)
    {
        $invoiceItems = null;
        $invoice = $this->invoicesRepository->find($id);
        if ($invoice) {
            $invoiceItems = $invoice->related('invoice_items');
        }

        $form = new Form;
        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();
        $defaults = [];

        $items = $form->addContainer('items');
        $i = 1;
        foreach ($invoiceItems as $item) {
            $itemName = "item_" . $item->id;

            $items->addTextArea($itemName, $this->translator->translate('invoices.form.change_invoice_items.item', ['i' => $i++]))
                ->setHtmlAttribute('placeholder', $this->translator->translate('invoices.form.change_invoice_items.placeholder'))
                ->setHtmlAttribute('class', 'simple-editor')
                ->setHtmlAttribute('rows', 2)
                ->setHtmlAttribute(
                    'data-html-editor',
                    ['btns' => [
                        ['viewHTML'],
                        ['undo', 'redo'],
                        ['formatting'],
                        ['strong', 'em', 'del'],
                    ]],
                );

            $defaults['items'][$itemName] = $item->text;
        }

        $form->addSubmit('send', $this->translator->translate('invoices.frontend.change_invoice_details.submit'));

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $items = $values['items'];
        foreach ($items as $key => $value) {
            $itemId = explode('_', $key)[1];
            $invoiceItem = $this->invoiceItemsRepository->find($itemId);

            $this->invoiceItemsRepository->update($invoiceItem, [
                'text' => $value,
            ]);
        }

        $this->onSuccess->__invoke($form);
    }
}
