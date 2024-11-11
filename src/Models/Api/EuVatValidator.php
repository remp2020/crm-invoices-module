<?php

namespace Crm\InvoicesModule\Models\Api;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\InvoicesModule\Repositories\VatIdConsultationsRepository;
use DragonBe\Vies\CheckVatResponse;
use DragonBe\Vies\Vies;
use DragonBe\Vies\ViesException;
use DragonBe\Vies\ViesServiceException;
use Nette\Utils\Json;
use Tracy\Debugger;

class EuVatValidator
{
    private ?\DateInterval $offlineValidationThreshold = null;

    public function __construct(
        private EuVatValidatorFactoryInterface $euVatValidatorFactory,
        private ApplicationConfig $applicationConfig,
        private VatIdConsultationsRepository $vatIdConsultationsRepository,
    ) {
    }

    /**
     * @param string $offlineValidationThreshold Sets threshold for offline validation when VIES service is offline.
     *                                           Use PHP interval string (eg. P3M for 3 months) to change interval
     *                                           or null to disable offline validation.
     */
    public function setOfflineValidationThreshold(?string $offlineValidationThreshold)
    {
        if ($offlineValidationThreshold === null) {
            $this->offlineValidationThreshold = null;
        } else {
            $this->offlineValidationThreshold = new \DateInterval($offlineValidationThreshold);
        }
    }

    public function validateVat(string $vatId): CheckVatResponse
    {
        $vies = $this->euVatValidatorFactory->create();

        $buyerVatIdSplit = $vies->splitVatId(Vies::filterVat($vatId));

        // required to get consultation number
        $supplierVatId = $this->applicationConfig->get('supplier_vat_id');
        if ($supplierVatId) {
            $supplierVatIdSplit = $vies->splitVatId(Vies::filterVat($supplierVatId));
        }

        try {
            $response = $vies->validateVat(
                countryCode: $buyerVatIdSplit['country'],
                vatNumber: $buyerVatIdSplit['id'],
                requesterCountryCode: $supplierVatIdSplit['country'] ?? '', // required to get consultation number
                requesterVatNumber: $supplierVatIdSplit['id'] ?? '', // required to get consultation number
            );
        } catch (ViesException|ViesServiceException $e) {
            // connection errors: eg. soap timeout, redirection limit
            // TODO: Maybe log only faultcode==='HTTP' SoapFaults?
            //       - see all calls add_soap_fault() in https://github.com/php/php-src/blob/master/ext/soap/php_http.c
            if ($e->getPrevious() instanceof \SoapFault) {
                /** @var \SoapFault $soapFault */
                $soapFault = $e->getPrevious();
                // log error to see how often VIES service times out
                Debugger::log(Json::encode([
                    'soap_fault_name' => $soapFault->faultname ?? null,
                    'soap_fault_code' => $soapFault->faultcode ?? null,
                    'soap_fault_string' => $soapFault->faultstring ?? null,
                    'soap_fault_actor' => $soapFault->faultactor ?? null,
                    'request_vat_id' => $vatId
                ]), Debugger::ERROR);

                // check previous validations (if caller set datetime threshold); CheckVatResponse contains date of validation
                if ($this->offlineValidationThreshold !== null) {
                    $lastVatIdValidConsultation = $this->vatIdConsultationsRepository->findLastByVatId(
                        $vatId,
                        (new \DateTime())->sub($this->offlineValidationThreshold),
                    );
                    if ($lastVatIdValidConsultation !== null) {
                        $consultationResponse = Json::decode($lastVatIdValidConsultation->response);
                        // export and parse date formats in CheckVatResponse do not match which causes exception
                        // - see CheckVatResponse::toArray() and CheckVatResponse::populate()
                        $consultationResponse->requestDate = $lastVatIdValidConsultation->date_of_validation;
                        return new CheckVatResponse($consultationResponse);
                    }
                }

                throw new EuVatValidatorException(
                    'Service for VAT ID validation (EU VIES) is not available at the moment, please try again later.',
                    EuVatValidatorException::SERVICE_UNAVAILABLE,
                    $soapFault
                );
            }

            // bad request errors: invalid country code; invalid VAT ID format; country not supported
            throw new EuVatValidatorException(
                $e->getMessage(),
                EuVatValidatorException::BAD_REQUEST,
            );
        }

        if (!empty($response->getIdentifier())) {
            // log consultation number; helpful in case of audit (present when VAT ID is valid)
            $this->vatIdConsultationsRepository->add(
                vatIdWithPrefix: $vatId,
                consultationNumber: $response->getIdentifier(),
                dateOfValidation: $response->getRequestDate(),
                response: $response->toArray(),
            );
        }

        return $response;
    }
}
