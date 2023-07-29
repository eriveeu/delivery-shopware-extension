<?php declare(strict_types=1);

namespace Erive\Delivery;

use Doctrine\DBAL\Connection;
use PDO;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Uuid\Uuid;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

class EriveDelivery extends Plugin
{
    const FIELD_SET = "custom_erive";
    const FIELD_STICKER_URL = self::FIELD_SET . "_stickerUrl";
    const FIELD_PARCEL_ID = self::FIELD_SET . "_parcelId";

    public function install(InstallContext $context): void
    {
        parent::install($context);

        $connection = $this->container->get(Connection::class);
        $date = date('Y-m-d') . ' 00:00:00.000';

        $setId =$this->getId($connection, 'custom_field_set', 'name', EriveDelivery::FIELD_SET) ?? Uuid::randomHex();
        $stickerId = $this->getId($connection, 'custom_field', 'name', EriveDelivery::FIELD_STICKER_URL) ?? Uuid::randomHex();
        $parcelId = $this->getId($connection, 'custom_field', 'name', EriveDelivery::FIELD_PARCEL_ID) ?? Uuid::randomHex();
        $relationId = $this->getId($connection, 'custom_field_set_relation', 'set_id', EriveDelivery::FIELD_SET) ?? Uuid::randomHex();
        
        $connection->exec("
            INSERT IGNORE INTO custom_field_set (id, name, config, active, app_id, position, global, created_at, updated_at)
            VALUES 
                (X'" . $setId . "', '" . EriveDelivery::FIELD_SET . "', '{\"label\":{\"en-GB\":\"ERIVE.delivery\"}}', 1, NULL, 1, 0, '" . $date . "', NULL);

            INSERT IGNORE INTO custom_field_set_relation (id, set_id, entity_name, created_at, updated_at)
            VALUES 
                (X'" . $relationId . "', X'" . $setId . "', 'order', '" . $date . "', NULL);

            INSERT IGNORE INTO custom_field (id, name, type, config, active, set_id, created_at, updated_at, allow_customer_write)
            VALUES
                (X'" . $stickerId . "', '" . EriveDelivery::FIELD_STICKER_URL . "', 'text', '{\"customFieldType\":\"text\",\"customFieldPosition\":1,\"label\":{\"en-GB\":\"ERIVE.delivery Label URL\"},\"placeholder\":{\"en-GB\":null},\"helpText\":{\"en-GB\":null},\"componentName\":\"sw-field\",\"type\":\"text\"}', 1, X'" . $setId . "', '" . $date . "', NULL, 0),
                (X'" . $parcelId . "', '" . EriveDelivery::FIELD_PARCEL_ID . "',   'text', '{\"customFieldType\":\"text\",\"customFieldPosition\":2,\"label\":{\"en-GB\":\"ERIVE.delivery Tracking Code\"},\"placeholder\":{\"en-GB\":null},\"helpText\":{\"en-GB\":null},\"componentName\":\"sw-field\",\"type\":\"text\"}', 1, X'" . $setId . "', '" . $date . "', NULL, 0);
        ");
    }

    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);

        if ($context->keepUserData()) {
            return;
        }

        $connection = $this->container->get(Connection::class);
        $connection->exec('DELETE FROM custom_field_set WHERE name = "' . EriveDelivery::FIELD_SET . '"');
    }

    private function getId(Connection $connection, string $table, string $field, string $value): ?string
    {
        $search = $connection->executeQuery('SELECT id FROM ' . $table . ' WHERE ' . $field . '="' . $value . '"');
        return $search->rowCount() > 0 ? Uuid::fromBytesToHex($search->fetch(PDO::FETCH_ASSOC)['id']) : null;
    }
}