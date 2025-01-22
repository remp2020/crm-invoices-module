<?php
declare(strict_types=1);

namespace Crm\InvoicesModule\Tests\Models\Vat;

use Crm\ApplicationModule\Seeders\CountriesSeeder;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\InvoicesModule\Models\Api\EuVatValidator;
use Crm\InvoicesModule\Models\Vat\VatModeDetector;
use Crm\InvoicesModule\Seeders\AddressTypesSeeder;
use Crm\PaymentsModule\Models\VatRate\VatMode;
use Crm\PaymentsModule\Repositories\VatRatesRepository;
use Crm\ProductsModule\Repositories\PostalFeesRepository;
use Crm\RespektCzModule\Seeders\PostalFeesSeeder;
use Crm\UsersModule\Models\Builder\UserBuilder;
use Crm\UsersModule\Repositories\AddressTypesRepository;
use Crm\UsersModule\Repositories\AddressesMetaRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use DragonBe\Vies\CheckVatResponse;
use Nette\Database\Table\ActiveRow;
use PHPUnit\Framework\Attributes\DataProvider;

class VatModeDetectorTest extends DatabaseTestCase
{
    private CountriesRepository $countriesRepository;
    private AddressesRepository $addressesRepository;
    private VatRatesRepository $vatRatesRepository;

    protected function setUp(): void
    {
        $this->refreshContainer();
        parent::setUp();
        $this->countriesRepository = $this->getRepository(CountriesRepository::class);
        $this->addressesRepository = $this->getRepository(AddressesRepository::class);
        $this->vatRatesRepository = $this->getRepository(VatRatesRepository::class);
    }

    #[DataProvider('dataForTest')]
    public function testDataProvider($companyId, $companyVatId, $validCompanyVatId, $euCountry, $paymentCountryCode, $expectedResult)
    {
        // only EU countries have assigned VAT rates
        if ($euCountry) {
            $defaultCountry = $this->countriesRepository->findByIsoCode('SK');
            $this->countriesRepository->setDefaultCountry('SK');
            $this->vatRatesRepository->upsert($defaultCountry, 10);
        }

        $paymentCountry = $this->countriesRepository->findByIsoCode($paymentCountryCode);

        $user = $this->createUser();
        $this->addCompanyInvoiceAddress($user, $paymentCountry, $companyId, $companyVatId);

        $vatDetector = $this->prepareVatDetector($paymentCountryCode, $validCompanyVatId);
        $this->assertEquals($expectedResult, $vatDetector->userVatMode($user));
    }

    public static function dataForTest()
    {
        return [
            'noCompanyId' => [
                'companyId' => null,
                'companyVatId' => null,
                'validCompanyVatId' => false,
                'euCountry' => true,
                'paymentCountryCode' => 'FR',
                'expectedResult' => VatMode::B2C,
            ],
            'companyIdWithNoCompanyVatId' => [
                'companyId' => 'company_id',
                'companyVatId' => null,
                'validCompanyVatId' => false,
                'euCountry' => true,
                'paymentCountryCode' => 'FR',
                'expectedResult' => VatMode::B2B,
            ],
            'companyIdWithInvalidCompanyVatId' => [
                'companyId' => 'company_id',
                'companyVatId' => 'company_vat_id',
                'validCompanyVatId' => false,
                'euCountry' => true,
                'paymentCountryCode' => 'FR',
                'expectedResult' => VatMode::B2B,
            ],
            'companyIdWithValidCompanyVatId_reverseCharge' => [
                'companyId' => 'company_id',
                'companyVatId' => 'company_vat_id',
                'validCompanyVatId' => true,
                'euCountry' => true,
                'paymentCountryCode' => 'FR',
                'expectedResult' => VatMode::B2BReverseCharge,
            ],
            'noCompanyIdWithValidCompanyVatId_noCompany_reverseCharge' => [
                'companyId' => '',
                'companyVatId' => 'company_vat_id',
                'validCompanyVatId' => true,
                'euCountry' => true,
                'paymentCountryCode' => 'FR',
                'expectedResult' => VatMode::B2BReverseCharge,
            ],
            'companyIdWithValidCompanyVatIdNonEurope' => [
                'companyId' => 'company_id',
                'companyVatId' => 'company_vat_id',
                'validCompanyVatId' => true,
                'euCountry' => false,
                'paymentCountryCode' => 'FR',
                'expectedResult' => VatMode::B2BNonEurope,
            ],
            'localCompanyIdWithValidCompanyVatId_b2b' => [
                'companyId' => 'company_id',
                'companyVatId' => 'company_vat_id',
                'validCompanyVatId' => true,
                'euCountry' => true,
                'paymentCountryCode' => 'SK',
                'expectedResult' => VatMode::B2B,
            ],
        ];
    }

    private function createUser(string $email = 'company@example.com'): ActiveRow
    {
        /** @var UserBuilder $userBuilder */
        $userBuilder = $this->inject(UserBuilder::class);
        $userRow = $userBuilder->createNew()
            ->setEmail($email)
            ->setPublicName($email)
            ->setPassword('secret', false)
            ->save();
        return $userRow;
    }

    private function addCompanyInvoiceAddress(
        ActiveRow $user,
        ActiveRow $country,
        ?string $companyId = null,
        ?string $companyVatId = null
    ): ActiveRow {
        return $this->addressesRepository->add(
            user: $user,
            type: 'invoice',
            firstName: $user->email,
            lastName: $user->email,
            address: 'Sample street',
            number: '123',
            city: 'Sample city',
            zip: '12345',
            countryId: $country->id,
            phoneNumber: '1234567890',
            companyId: $companyId,
            companyVatId: $companyVatId,
        );
    }

    private function prepareVatDetector(string $paymentCountryCode, bool $validVatIdResponse = false): VatModeDetector
    {
        $euVatValidator = \Mockery::mock(EuVatValidator::class)
            ->shouldReceive('validateVat')
            ->andReturn(new CheckVatResponse([
                'countryCode' => $paymentCountryCode,
                'vatNumber' => 'some_vat_id',
                'requestDate' => new \DateTime(),
                'valid' => $validVatIdResponse,
            ]))
            ->getMock();


        return $this->container->createInstance(VatModeDetector::class, [
            'euVatValidator' => $euVatValidator,
        ]);
    }

    protected function requiredRepositories(): array
    {
        return [
            AddressesRepository::class,
            AddressTypesRepository::class,
            AddressesMetaRepository::class,
            VatRatesRepository::class,
            CountriesRepository::class,
            PostalFeesRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            CountriesSeeder::class,
            PostalFeesSeeder::class,
            AddressTypesSeeder::class,
        ];
    }
}
