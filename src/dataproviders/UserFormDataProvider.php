<?php

namespace Crm\InvoicesModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\ApplicationModule\Selection;
use Crm\UsersModule\DataProvider\UserFormDataProviderInterface;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Application\UI\Form;

class UserFormDataProvider implements UserFormDataProviderInterface
{
    private $usersRepository;

    public function __construct(UsersRepository $usersRepository)
    {
        $this->usersRepository = $usersRepository;
    }

    /**
     * @param array $params
     * @return Selection
     * @throws DataProviderException
     */
    public function provide(array $params): Form
    {
        if (!isset($params['form'])) {
            throw new DataProviderException('missing [form] within data provider params');
        }
        if (!($params['form'] instanceof Form)) {
            throw new DataProviderException('invalid type of provided form: ' . get_class($params['form']));
        }

        $form = $params['form'];

        $form->addGroup('invoices.admin.user_form.invoices');
        $container = $form->addContainer('invoices');
        $container->addCheckbox('invoice', 'invoices.admin.user_form.invoice');
        $container->addCheckbox('disable_auto_invoice', 'invoices.admin.user_form.disable_autoinvoice');

        if ($params['user']) {
            $form->setDefaults([
                'invoices' => [
                    'invoice' => $params['user']->invoice,
                    'disable_auto_invoice' => $params['user']->disable_auto_invoice,
                ],
            ]);
        }

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $user = $this->usersRepository->findBy('email', $values->email);
        $this->usersRepository->update($user, [
            'invoice' => $values->invoices->invoice,
            'disable_auto_invoice' => $values->invoices->disable_auto_invoice,
        ]);
    }
}
