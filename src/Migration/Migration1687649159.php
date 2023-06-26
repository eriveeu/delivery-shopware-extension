<?php declare(strict_types=1);

namespace Erive\Delivery\Migration;

use Doctrine\DBAL\Connection;
use Erive\Delivery\EriveDelivery;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
// use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
// use Shopware\Core\Migration\Traits\Translations;

class Migration1687649159 extends MigrationStep
{
    // use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1687649159;
    }

    public function update(Connection $connection): void
    {
        $setId = Uuid::randomHex();
        
        $connection->exec("INSERT INTO `custom_field_set` (`id`, `name`, `config`, `active`, `app_id`, `position`, `global`, `created_at`, `updated_at`) VALUES (X'" . $setId . "', '" . EriveDelivery::FIELD_SET . "', X'7B226C6162656C223A7B22656E2D4742223A6E756C6C7D7D', 1, NULL, 1, 0, '2023-06-24 00:00:00.000', NULL);");
        $connection->exec("INSERT INTO `custom_field_set_relation` (`id`, `set_id`, `entity_name`, `created_at`, `updated_at`) VALUES (X'" . Uuid::randomHex() . "', X'" . $setId . "', 'order', '2023-06-24 00:00:00.000', NULL);");
        $connection->exec("INSERT INTO `custom_field` (`id`, `name`, `type`, `config`, `active`, `set_id`, `created_at`, `updated_at`, `allow_customer_write`) VALUES (X'" . Uuid::randomHex() . "', '" . EriveDelivery::FIELD_STICKER_URL . "', 'text', X'7B22637573746F6D4669656C6454797065223A2274657874222C22637573746F6D4669656C64506F736974696F6E223A322C226C6162656C223A7B22656E2D4742223A2245524956452E64656C697665727920537469636B65722055524C227D2C22706C616365686F6C646572223A7B22656E2D4742223A6E756C6C7D2C2268656C7054657874223A7B22656E2D4742223A6E756C6C7D2C22636F6D706F6E656E744E616D65223A2273772D6669656C64222C2274797065223A2274657874227D', 1, X'" . $setId . "', '2023-06-24 00:00:00.000', NULL, 0);");
        $connection->exec("INSERT INTO `custom_field` (`id`, `name`, `type`, `config`, `active`, `set_id`, `created_at`, `updated_at`, `allow_customer_write`) VALUES (X'" . Uuid::randomHex() . "', '" . EriveDelivery::FIELD_PARCEL_ID . "', 'text', X'7B22637573746F6D4669656C6454797065223A2274657874222C22637573746F6D4669656C64506F736974696F6E223A312C226C6162656C223A7B22656E2D4742223A2245524956452E64656C69766572792050617263656C204944227D2C22706C616365686F6C646572223A7B22656E2D4742223A6E756C6C7D2C2268656C7054657874223A7B22656E2D4742223A6E756C6C7D2C22636F6D706F6E656E744E616D65223A2273772D6669656C64222C2274797065223A2274657874227D', 1, X'" . $setId . "', '2023-06-24 00:00:00.000', NULL, 0);");
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
