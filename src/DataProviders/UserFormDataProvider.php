<?php

namespace Crm\InvoicesModule\DataProviders;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\UsersModule\DataProviders\UserFormDataProviderInterface;
use Crm\UsersModule\Repositories\UsersRepository;
use Tomaj\Hermes\Emitter;

class UserFormDataProvider implements UserFormDataProviderInterface
{
    public function __construct(
        private Emitter $hermesEmitter,
        private PaymentsRepository $paymentsRepository,
        private UsersRepository $usersRepository,
    ) {
    }

    /**
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

        $changedInvoicing = [];
        if ((bool) $user->invoice !== (bool) $values->invoices->invoice) {
            $changedInvoicing['invoice'] = $values->invoices->invoice;
        }
        if ((bool) $user->disable_auto_invoice !== (bool) $values->invoices->disable_auto_invoice) {
            $changedInvoicing['disable_auto_invoice'] = $values->invoices->disable_auto_invoice;
        }

        if (!empty($changedInvoicing)) {
            $this->usersRepository->update($user, $changedInvoicing);

            // if invoicing was enabled by this change, generate invoice for all invoiceable payments
            if ($values->invoices->invoice && !$values->invoices->disable_auto_invoice) {
                $payments = $this->paymentsRepository->userPayments($user->id)
                    ->where('invoice_id', null)
                    ->fetchAll();

                foreach ($payments as $payment) {
                    $this->hermesEmitter->emit(new HermesMessage('generate_invoice', [
                        'payment_id' => $payment->id
                    ]), HermesMessage::PRIORITY_LOW);
                }
            }
        }
    }
}
