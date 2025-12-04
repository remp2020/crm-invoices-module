<?php

namespace Crm\InvoicesModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\UI\Form;
use Crm\UsersModule\DataProviders\AddressFormDataProviderInterface;
use Nette\Forms\Controls\SelectBox;
use Nette\Forms\Controls\TextInput;

class AddressFormDataProvider implements AddressFormDataProviderInterface
{
    public function provide(array $params): Form
    {
        if (!isset($params['form'])) {
            throw new DataProviderException('missing [form] within data provider params');
        }

        /** @var Form $form */
        $form = $params['form'];

        /** @var SelectBox $type */
        $type = $form->getComponent('type', false);

        if ($type) {
            $firstName = $form->getComponent('first_name', false);
            $lastName = $form->getComponent('last_name', false);

            $type->addCondition(Form::Equal, 'invoice')
                ->addConditionOn($firstName, Form::Blank)
                ->toggle($firstName->getOption('id'), false);

            $type->addCondition(Form::Equal, 'invoice')
                ->addConditionOn($lastName, Form::Blank)
                ->toggle($lastName->getOption('id'), false);

            /** @var TextInput $companyName */
            $companyName = $form->getComponent('company_name', false);
            $companyName
                ->setMaxLength(150)
                ->addConditionOn($type, Form::Equal, 'invoice')
                ->setRequired('invoices.form.invoice.required.company_name');

            /** @var TextInput $street */
            $street = $form->getComponent('street', false);
            $street
                ->addConditionOn($type, Form::Equal, 'invoice')
                ->setRequired('invoices.form.invoice.required.street');

            /** @var TextInput $number */
            $number = $form->getComponent('number', false);
            $number
                ->addConditionOn($type, Form::Equal, 'invoice')
                ->setRequired('invoices.form.invoice.required.number');

            /** @var TextInput $city */
            $city = $form->getComponent('city', false);
            $city
                ->addConditionOn($type, Form::Equal, 'invoice')
                ->setRequired('invoices.form.invoice.required.city');

            /** @var TextInput $zip */
            $zip = $form->getComponent('zip', false);
            $zip
                ->addConditionOn($type, Form::Equal, 'invoice')
                ->setRequired('invoices.form.invoice.required.zip');
        }

        return $form;
    }
}
