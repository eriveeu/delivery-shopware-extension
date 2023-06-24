<?php declare(strict_types=1);

namespace Erive\Delivery;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

class EriveDelivery extends Plugin
{
    public function install(InstallContext $context): void
    {
        parent::install($context);
    }
}