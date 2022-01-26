<?php

namespace Crm\InvoicesModule\Forms;

use Crm\InvoicesModule\Repository\InvoiceItemsRepository;
use Crm\InvoicesModule\Repository\InvoicesRepository;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class ChangeInvoiceItemsFormFactory
{
    public $onSuccess;

    private $translator;

    private $invoicesRepository;


    private $invoiceItemsRepository;

    public function __construct(
        ITranslator $translator,
        InvoicesRepository $invoicesRepository,
        InvoiceItemsRepository $invoiceItemsRepository
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
                ->setAttribute('placeholder', $this->translator->translate('invoices.form.change_invoice_items.placeholder'))
                ->setAttribute('class', 'simple-editor')
                ->setAttribute('rows', 2)
                ->setAttribute(
                    'data-html-editor',
                    ['btns' => [
                        ['viewHTML'],
                        ['undo', 'redo'],
                        ['formatting'],
                        ['strong', 'em', 'del']
                    ]]
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
                'text' => $value
            ]);
        }

        $this->onSuccess->__invoke($form);
    }
}
