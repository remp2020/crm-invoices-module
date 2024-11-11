<?php

namespace Crm\InvoicesModule\Tests\Api;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Repositories\ConfigsRepository;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\InvoicesModule\Models\Api\EuVatValidator;
use Crm\InvoicesModule\Models\Api\EuVatValidatorException;
use Crm\InvoicesModule\Models\Api\EuVatValidatorFactoryInterface;
use Crm\InvoicesModule\Repositories\VatIdConsultationsRepository;
use Crm\InvoicesModule\Seeders\ConfigsSeeder;
use DragonBe\Vies\CheckVatResponse;
use DragonBe\Vies\Vies;
use DragonBe\Vies\ViesException;
use DragonBe\Vies\ViesServiceException;
use Mockery;
use Nette\Utils\Json;

class EuVatValidatorTest extends DatabaseTestCase
{
    private ConfigsRepository $configsRepository;
    private VatIdConsultationsRepository $vatIdConsultationsRepository;

    protected function requiredRepositories(): array
    {
        return [
            VatIdConsultationsRepository::class,
            ConfigsRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            ConfigsSeeder::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->configsRepository = $this->getRepository(ConfigsRepository::class);
        $this->vatIdConsultationsRepository = $this->getRepository(VatIdConsultationsRepository::class);

        $this->assertEquals(0, $this->vatIdConsultationsRepository->totalCount());
    }

    public function testSuccess(): void
    {
        $buyerVatId = 'SK123456789';
        $buyerVatCountry = 'SK';
        $buyerVatIdWithoutPrefix = '123456789';

        // set supplier VAT id (to get identifier / consultation number)
        $supplierVatId = 'SK9987654321';

        $supplierVatConfig = $this->configsRepository->findBy('name', 'supplier_vat_id');
        $this->configsRepository->update($supplierVatConfig, ['value' => $supplierVatId]);

        $euVatValidator = $this->getEuVatValidatorWithMockedViesService(
            isAlive: true,
            isValid: true,
            buyerVatId: $buyerVatId,
            supplierVatId: $supplierVatId,
        );

        $response = $euVatValidator->validateVat($buyerVatId);
        $this->assertTrue($response->isValid());
        $this->assertEquals($buyerVatCountry, $response->getCountryCode());
        $this->assertEquals($buyerVatIdWithoutPrefix, $response->getVatNumber());
        $this->assertNotEmpty($response->getIdentifier());

        // one new consultation number
        $this->assertEquals(1, $this->vatIdConsultationsRepository->totalCount());
        // find record by vat ID
        $consultationRow = $this->vatIdConsultationsRepository->findBy('vat_id', $buyerVatId);
        $this->assertEquals($response->getIdentifier(), $consultationRow->consultation_number);
        $this->assertEquals(
            $response->toArray(),
            Json::decode($consultationRow->response, Json::FORCE_ARRAY)
        );
    }

    public function testSuccessWithoutConsultationNumber(): void
    {
        $buyerVatId = 'SK123456789';
        $buyerVatCountry = 'SK';
        $buyerVatIdWithoutPrefix = '123456789';

        $euVatValidator = $this->getEuVatValidatorWithMockedViesService(
            isAlive: true,
            isValid: true,
            buyerVatId: $buyerVatId,
        );

        $response = $euVatValidator->validateVat($buyerVatId);
        $this->assertTrue($response->isValid());
        $this->assertEquals($buyerVatCountry, $response->getCountryCode());
        $this->assertEquals($buyerVatIdWithoutPrefix, $response->getVatNumber());
        $this->assertEmpty($response->getIdentifier());

        // no consultation number stored
        $this->assertEquals(0, $this->vatIdConsultationsRepository->totalCount());
    }


    public function testInvalidVatId(): void
    {
        $buyerVatId = 'SK123456789';
        $buyerVatCountry = 'SK';
        $buyerVatIdWithoutPrefix = '123456789';

        $euVatValidator = $this->getEuVatValidatorWithMockedViesService(
            isAlive: true,
            buyerVatId: $buyerVatId,
            isValid: false,
        );

        $response = $euVatValidator->validateVat($buyerVatId);
        $this->assertFalse($response->isValid());
        $this->assertEquals($buyerVatCountry, $response->getCountryCode());
        $this->assertEquals($buyerVatIdWithoutPrefix, $response->getVatNumber());

        // no new consultation number
        $this->assertEquals(0, $this->vatIdConsultationsRepository->totalCount());
    }

    public function testViesUnavailable(): void
    {
        $buyerVatId = 'SK123456789';
        $euVatValidator = $this->getEuVatValidatorWithMockedViesService(
            isAlive: false,
            buyerVatId: $buyerVatId,
        );

        $this->expectExceptionObject(new EuVatValidatorException(
            'Service for VAT ID validation (EU VIES) is not available at the moment, please try again later.',
            EuVatValidatorException::SERVICE_UNAVAILABLE
        ));
        $euVatValidator->validateVat($buyerVatId);

        // no new consultation number
        $this->assertEquals(0, $this->vatIdConsultationsRepository->totalCount());
    }

    public function testViesUnavailableAndOfflineValidationValid(): void
    {
        $buyerVatId = 'SK123456789';
        $buyerVatCountry = 'SK';
        $buyerVatIdWithoutPrefix = '123456789';

        // add previous validation
        $consultationNumber = (string) random_int(1, 100);
        $requestDate = new \DateTime('2 months ago');
        $this->vatIdConsultationsRepository->add(
            $buyerVatId,
            $consultationNumber,
            $requestDate,
            [
                'vatNumber' => $buyerVatIdWithoutPrefix,
                'countryCode' => $buyerVatCountry,
                'requestIdentifier' => $consultationNumber,
                'requestDate' => $requestDate->format(CheckVatResponse::VIES_DATETIME_FORMAT),
                'valid' => true,
            ],
        );
        // check there is only this one consultation
        $this->assertEquals(1, $this->vatIdConsultationsRepository->totalCount());

        // mock validator
        $euVatValidator = $this->getEuVatValidatorWithMockedViesService(
            isAlive: false,
            buyerVatId: $buyerVatId,
        );

        $response = $euVatValidator->validateVat($buyerVatId);
        $this->assertTrue($response->isValid());
        $this->assertEquals($buyerVatCountry, $response->getCountryCode());
        $this->assertEquals($buyerVatIdWithoutPrefix, $response->getVatNumber());
        $this->assertEquals($response->getIdentifier(), $consultationNumber);

        // NO new consultation number (we didn't consult online with VIES)
        $this->assertEquals(1, $this->vatIdConsultationsRepository->totalCount());
    }

    public function testViesUnavailableAndOfflineValidationInvalid(): void
    {
        $buyerVatId = 'SK123456789';
        $buyerVatCountry = 'SK';
        $buyerVatIdWithoutPrefix = '123456789';

        // add previous validation
        $consultationNumber = (string) random_int(1, 100);
        $requestDate = new \DateTime('2 months ago');
        $this->vatIdConsultationsRepository->add(
            $buyerVatId,
            $consultationNumber,
            $requestDate,
            [
                'vatNumber' => $buyerVatIdWithoutPrefix,
                'countryCode' => $buyerVatCountry,
                'requestIdentifier' => $consultationNumber,
                'requestDate' => $requestDate->format(CheckVatResponse::VIES_DATETIME_FORMAT),
                'valid' => false,
            ],
        );
        // check there is only this one consultation
        $this->assertEquals(1, $this->vatIdConsultationsRepository->totalCount());

        // mock validator
        $euVatValidator = $this->getEuVatValidatorWithMockedViesService(
            isAlive: false,
            buyerVatId: $buyerVatId,
        );

        $response = $euVatValidator->validateVat($buyerVatId);
        $this->assertFalse($response->isValid());
        $this->assertEquals($buyerVatCountry, $response->getCountryCode());
        $this->assertEquals($buyerVatIdWithoutPrefix, $response->getVatNumber());
        $this->assertEquals($response->getIdentifier(), $consultationNumber);

        // NO new consultation number (we didn't consult online with VIES)
        $this->assertEquals(1, $this->vatIdConsultationsRepository->totalCount());
    }

    public function testViesUnavailableAndOfflineValidationTooOld(): void
    {
        $buyerVatId = 'SK123456789';
        $buyerVatCountry = 'SK';
        $buyerVatIdWithoutPrefix = '123456789';

        // add previous validation
        $consultationNumber = (string) random_int(1, 100);
        $requestDate = new \DateTime('4 months ago');
        $this->vatIdConsultationsRepository->add(
            $buyerVatId,
            $consultationNumber,
            $requestDate,
            [
                'vatNumber' => $buyerVatIdWithoutPrefix,
                'countryCode' => $buyerVatCountry,
                'requestIdentifier' => $consultationNumber,
                'requestDate' => $requestDate->format(CheckVatResponse::VIES_DATETIME_FORMAT),
                'valid' => false,
            ],
        );
        // check there is only this one consultation
        $this->assertEquals(1, $this->vatIdConsultationsRepository->totalCount());

        // mock validator
        $euVatValidator = $this->getEuVatValidatorWithMockedViesService(
            isAlive: false,
            buyerVatId: $buyerVatId,
        );

        // consultation is too old; return VIES UNAVAILABLE error
        $this->expectExceptionObject(new EuVatValidatorException(
            'Service for VAT ID validation (EU VIES) is not available at the moment, please try again later.',
            EuVatValidatorException::SERVICE_UNAVAILABLE
        ));
        $euVatValidator->validateVat($buyerVatId);

        // NO new consultation number (we didn't consult online with VIES)
        $this->assertEquals(1, $this->vatIdConsultationsRepository->totalCount());
    }

    public function testViesUnavailableAndOfflineValidationDisabled(): void
    {
        $buyerVatId = 'SK123456789';
        $buyerVatCountry = 'SK';
        $buyerVatIdWithoutPrefix = '123456789';

        // add previous validation
        $consultationNumber = (string) random_int(1, 100);
        $requestDate = new \DateTime('2 months ago');
        $this->vatIdConsultationsRepository->add(
            $buyerVatId,
            $consultationNumber,
            $requestDate,
            [
                'vatNumber' => $buyerVatIdWithoutPrefix,
                'countryCode' => $buyerVatCountry,
                'requestIdentifier' => $consultationNumber,
                'requestDate' => $requestDate->format(CheckVatResponse::VIES_DATETIME_FORMAT),
                'valid' => true,
            ],
        );
        // check there is only this one consultation
        $this->assertEquals(1, $this->vatIdConsultationsRepository->totalCount());

        // mock validator
        $euVatValidator = $this->getEuVatValidatorWithMockedViesService(
            isAlive: false,
            buyerVatId: $buyerVatId,
        );
        // NULL disabled offline validation
        $euVatValidator->setOfflineValidationThreshold(null);

        $this->expectExceptionObject(new EuVatValidatorException(
            'Service for VAT ID validation (EU VIES) is not available at the moment, please try again later.',
            EuVatValidatorException::SERVICE_UNAVAILABLE
        ));
        $euVatValidator->validateVat($buyerVatId);

        // NO new consultation number (we didn't consult online with VIES)
        $this->assertEquals(1, $this->vatIdConsultationsRepository->totalCount());
    }

    public function testViesReturnedException(): void
    {
        $euVatValidator = $this->getEuVatValidatorWithMockedViesService(
            isAlive: true,
        );

        $this->expectException(EuVatValidatorException::class);
        $euVatValidator->validateVat('');

        // no new consultation number
        $this->assertEquals(0, $this->vatIdConsultationsRepository->totalCount());
    }

    /**
     * Provides EuVatValidator with mocked Vies service based on provided configuration
     */
    private function getEuVatValidatorWithMockedViesService(
        bool $isAlive = false,
        ?string $buyerVatId = null,
        bool $isValid = false,
        ?string $supplierVatId = null,
    ): EuVatValidator {
        $viesMock = Mockery::mock(Vies::class);

        // if VIES is not alive, SoapFault is returned within ViesServiceException
        if (!$isAlive) {
            // not mocking split; let Vies properly split provided VAT ID
            $splitBuyerVatId = (new Vies())->splitVatId($buyerVatId);
            $viesMock->shouldReceive('splitVatId')
                ->with($buyerVatId)
                ->andReturn($splitBuyerVatId)
                ->once()
                ->getMock();
            $e = new ViesServiceException('exception message', 0, new \SoapFault('HTTP', 'soap fault message'));
            $viesMock->shouldReceive('validateVat')
                ->with($splitBuyerVatId['country'], $splitBuyerVatId['id'], '', '')
                ->andThrow($e)
                ->once()
                ->getMock();
        } else {
            if ($buyerVatId !== null) {
                // not mocking split; let Vies properly split provided VAT ID
                $splitBuyerVatId = (new Vies())->splitVatId($buyerVatId);
                $viesMock->shouldReceive('splitVatId')
                    ->with($buyerVatId)
                    ->andReturn($splitBuyerVatId)
                    ->once()
                    ->getMock();

                if ($supplierVatId !== null) {
                    // not mocking split; let Vies properly split provided VAT ID
                    $splitSupplierVatId = (new Vies())->splitVatId($supplierVatId);
                    $viesMock->shouldReceive('splitVatId')
                        ->with($supplierVatId)
                        ->andReturn($splitSupplierVatId)
                        ->once()
                        ->getMock();
                } else {
                    $splitSupplierVatId = ['country' => '', 'id' => ''];
                }

                // mock Vies->isValid()
                $viesMock->shouldReceive('isValid')
                    ->andReturn($isValid)
                    ->getMock();

                // mock Vies->validateVat()
                $checkVatResponse = new CheckVatResponse([
                    'countryCode' => $splitBuyerVatId['country'],
                    'vatNumber' => $splitBuyerVatId['id'],
                    'requestDate' => new \DateTime(),
                    'valid' => $isValid,
                    // identifier is set only if supplier VAT ID is configured
                    'requestIdentifier' => $supplierVatId ? 'some-random-unique-id' : '',
                ]);
                $viesMock->shouldReceive('validateVat')
                    ->with(
                        $splitBuyerVatId['country'],
                        $splitBuyerVatId['id'],
                        $splitSupplierVatId['country'],
                        $splitSupplierVatId['id'],
                    )
                    ->andReturn($checkVatResponse)
                    ->once()
                    ->getMock();
            } else {
                // missing vat id returns exception
                $viesMock->shouldReceive('splitVatId')
                    ->with($buyerVatId)
                    ->andReturn(['country' => '', 'id' => ''])
                    ->once()
                    ->getMock();
                $viesMock->shouldReceive('validateVat')
                    ->with('', '', '', '')
                    ->andThrow(ViesException::class)
                    ->once()
                    ->getMock();
            }
        }

        // mock & return
        $euVatValidatorFactory = Mockery::mock(EuVatValidatorFactoryInterface::class);
        $euVatValidatorFactory->shouldReceive('create')
            ->andReturn($viesMock)
            ->getMock();

        $euVatValidator = new EuVatValidator(
            $euVatValidatorFactory,
            $this->inject(ApplicationConfig::class),
            $this->getRepository(VatIdConsultationsRepository::class),
        );

        // this is default used in production;
        $euVatValidator->setOfflineValidationThreshold('P3M');

        return $euVatValidator;
    }
}
