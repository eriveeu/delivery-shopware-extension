<?php declare(strict_types=1);

namespace Erive\Delivery;

use PDO;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

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
    }

    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);

        if ($context->keepUserData()) {
            return;
        }

        $connection = $this->container->get(Connection::class);
        $setId = Uuid::fromBytesToHex($this->getId($connection, 'custom_field_set', 'name', EriveDelivery::FIELD_SET));
        $connection->exec('DELETE FROM custom_field WHERE name = "' . EriveDelivery::FIELD_STICKER_URL . '"');
        $connection->exec('DELETE FROM custom_field WHERE name = "' . EriveDelivery::FIELD_PARCEL_ID . '"');
        $connection->exec('DELETE FROM custom_field_set WHERE id = "' . $setId . '"');
        $connection->exec('DELETE FROM custom_field_set_relation WHERE set_id = "' . $setId . '"');
    }

    private function getId(Connection $connection, string $table, string $field, string $value): ?string
    {
        $search = $connection->executeQuery('SELECT id FROM ' . $table . ' WHERE ' . $field . '="' . $value . '"');
        return $search->rowCount() > 0 ? $search->fetch(PDO::FETCH_ASSOC)['id'] : null;
    }
}