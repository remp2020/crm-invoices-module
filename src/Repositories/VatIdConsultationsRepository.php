<?php

namespace Crm\InvoicesModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Json;

final class VatIdConsultationsRepository extends Repository
{
    protected $tableName = 'vat_id_consultations';

    public function add(string $vatIdWithPrefix, string $consultationNumber, \DateTime $dateOfValidation, array $response)
    {
        $consultation = $this->getTable()->where([
            'consultation_number' => $consultationNumber,
        ])->fetch();

        if ($consultation) {
            // If there are two requests to the same VAT ID, EU system can use the cached response and return the same
            // consultation number as before. We don't allow two records with the same consultation number, so we halt
            // early and don't try to insert anything.
            return;
        }

        $this->insert([
            'vat_id' => $vatIdWithPrefix,
            'consultation_number' => $consultationNumber,
            'date_of_validation' => $dateOfValidation,
            'response' => Json::encode($response),
        ]);
    }

    public function update(ActiveRow &$row, $data): bool
    {
        throw new \Exception('Update of VAT ID consultation is not allowed.');
    }

    /**
     * @param \DateTime|null $dateOfValidationThreshold Provide if
     */
    public function findLastByVatId(string $vatId, \DateTime $dateOfValidationThreshold = null): ?ActiveRow
    {
        $selection = $this->getTable()->where('vat_id = ?', $vatId)->order('date_of_validation DESC');
        if ($dateOfValidationThreshold !== null) {
            $selection->where('date_of_validation >= ?', $dateOfValidationThreshold);
        }
        return $selection->fetch();
    }
}
