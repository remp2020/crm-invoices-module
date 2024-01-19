<?php

use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\ApplicationModule\Application\Core;
use Phinx\Migration\AbstractMigration;

class InvoicesCountryIdNullable extends AbstractMigration
{
    public function up()
    {
        $this->table('invoices')
            ->changeColumn('buyer_country_id', 'integer', ['null' => true])
            ->update();
    }

    public function down()
    {
        // run this part only if you are sure that
        // setting default country as buyer's country for all invoices without country is correct step
        if ($run = false) {
            /** @var Core $app */
            $app = $GLOBALS['application'] ?? null;
            if (!$app) {
                throw new \Exception("Unable to load application from \$GLOBALS['application'] variable, cannot load default country.");
            }

            /** @var CountriesRepository $countriesRepository */
            $countriesRepository = $app->getContainer()->getByType(CountriesRepository::class);
            $defaultCountryId = $countriesRepository->defaultCountry()->id;

            $this->query("UPDATE invoices SET buyer_country_id={$defaultCountryId} WHERE buyer_country_id IS NULL;");
        }

        // change column back to nullable
        $this->table('invoices')
            ->changeColumn('buyer_country_id', 'integer', ['null' => false])
            ->update();
    }
}
