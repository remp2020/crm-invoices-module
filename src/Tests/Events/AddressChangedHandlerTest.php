<?php

namespace Crm\InvoicesModule\Tests\Events;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\InvoicesModule\Events\AddressChangedHandler;
use Crm\InvoicesModule\Seeders\AddressTypesSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Events\AddressChangedEvent;
use Crm\UsersModule\Events\UserMetaEvent;
use Crm\UsersModule\Repository\AddressTypesRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\CountriesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\ActiveRow;

class AddressChangedHandlerTest extends DatabaseTestCase
{
    private AddressChangedHandler $addressChangedHandler;

    private UsersRepository $usersRepository;

    private ?ActiveRow $user = null;

    protected function requiredRepositories(): array
    {
        return [
            AddressesRepository::class,
            AddressTypesRepository::class,
            CountriesRepository::class,
            UsersRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            AddressTypesSeeder::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->addressChangedHandler = $this->inject(AddressChangedHandler::class);
    }

    public function testSuccessUser()
    {
        $user = $this->getUser();
        // set user's invoice flag to false
        $this->usersRepository->update($user, ['invoice' => false]);
        $this->assertEquals(0, $user->invoice);

        $address = $this->addUserAddress('invoice');
        $event = new AddressChangedEvent($address);

        // handle event
        $this->addressChangedHandler->handle($event);

        // refresh user; event handler made changes
        $user = $this->usersRepository->find($user->id);

        $this->assertEquals(0, $user->invoice);
    }


    public function testSuccessAdmin()
    {
        $user = $this->getUser();
        // set user's invoice flag to false
        $this->usersRepository->update($user, ['invoice' => false]);
        $this->assertEquals(0, $user->invoice);

        $address = $this->addUserAddress('invoice');
        $event = new AddressChangedEvent($address, true);

        // handle event
        $this->addressChangedHandler->handle($event);

        // refresh user; event handler made changes
        $user = $this->usersRepository->find($user->id);

        $this->assertEquals(1, $user->invoice);
    }

    public function testAddressIncorrectTypeUser()
    {
        $user = $this->getUser();
        // set user's invoice flag to false
        $this->usersRepository->update($user, ['invoice' => false]);
        $this->assertEquals(0, $user->invoice);

        // add address that is not an invoice address type
        /** @var AddressTypesRepository $addressTypesRepository */
        $addressTypesRepository = $this->inject(AddressTypesRepository::class);
        $addressTypesRepository->add('not-an-invoice-type', 'Not an invoice address type');
        $address = $this->addUserAddress('not-an-invoice-type');

        $event = new AddressChangedEvent($address, false);
        // handle event
        $this->addressChangedHandler->handle($event);
        // refresh user; event handler made changes
        $user = $this->usersRepository->find($user->id);
        // invoice flag not changed; incorrect address stopped processing
        $this->assertEquals(0, $user->invoice);
    }

    public function testAddressIncorrectTypeAdmin()
    {
        $user = $this->getUser();
        // set user's invoice flag to false
        $this->usersRepository->update($user, ['invoice' => false]);
        $this->assertEquals(0, $user->invoice);

        // add address that is not an invoice address type
        /** @var AddressTypesRepository $addressTypesRepository */
        $addressTypesRepository = $this->inject(AddressTypesRepository::class);
        $addressTypesRepository->add('not-an-invoice-type', 'Not an invoice address type');
        $address = $this->addUserAddress('not-an-invoice-type');

        $event = new AddressChangedEvent($address, true);
        // handle event
        $this->addressChangedHandler->handle($event);
        // refresh user; event handler made changes
        $user = $this->usersRepository->find($user->id);
        // invoice flag not changed; incorrect address stopped processing
        $this->assertEquals(0, $user->invoice);
    }

    public function testIncorrectEventType()
    {
        $user = $this->getUser();
        $event = new UserMetaEvent($user->id, 'foo', 'bar'); // just random event which doesn't need special entity to mock

        $this->expectExceptionObject(new \Exception(
            'Invalid type of event. Expected: [Crm\UsersModule\Events\AddressChangedEvent]. Received: [Crm\UsersModule\Events\UserMetaEvent].'
        ));

        // handle event
        $this->addressChangedHandler->handle($event);
    }

    /* *******************************************************************
     * Helper functions
     * ***************************************************************** */

    private function getUser(): ActiveRow
    {
        if ($this->user) {
            return $this->user;
        }

        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);

        $user = $userManager->addNewUser('example@example.com', false, 'unknown', null, false);
        $this->usersRepository->update($user, [
            'invoice' => true,
            'disable_auto_invoice' => false
        ]);
        return $this->user = $user;
    }

    private function addUserAddress(string $addressType): ActiveRow
    {
        /** @var CountriesRepository $countriesRepository */
        $countriesRepository = $this->getRepository(CountriesRepository::class);
        $country = $countriesRepository->add('SK', 'Slovensko', null);

        /** @var AddressesRepository $addressesRepository */
        $addressesRepository = $this->inject(AddressesRepository::class);
        return $addressesRepository->add(
            $this->getUser(),
            $addressType,
            'Someone',
            'Somewhat',
            'Very Long Street',
            '42',
            'Neverville',
            '13579',
            $country->id,
            '+99987654321',
        );
    }
}
