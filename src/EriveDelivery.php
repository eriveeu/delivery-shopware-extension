<?php

declare(strict_types=1);

namespace Erive\Delivery;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

class EriveDelivery extends Plugin
{
    public const CUSTOM_FIELD_SET_PREFIX_ID = "0209e10e222e47c0b98ab938f413171e";
    public const CUSTOM_FIELD_SET_PREFIX = "custom_erive";
    public const CUSTOM_FIELD_PARCEL_LABEL_URL_ID = "6f5f23d757fd4ea98aed3260db77e355";
    public const CUSTOM_FIELD_PARCEL_LABEL_URL = self::CUSTOM_FIELD_SET_PREFIX . "_stickerUrl";
    public const CUSTOM_FIELD_PARCEL_NUMBER_ID = "fa5870c8bd194992b316d2f4be2eb009";
    public const CUSTOM_FIELD_PARCEL_NUMBER = self::CUSTOM_FIELD_SET_PREFIX . "_parcelId";

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        $this->createCustomFields($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->removeCustomFields($uninstallContext->getContext());
    }

    public function removeCustomFields(Context $context): void
    {
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        foreach ($this->getCustomFieldSetIds($context)->getIds() as $customFieldSetId) {
            $customFieldSetRepository->delete([['id' => $customFieldSetId]], $context);
        }
    }

    public function createCustomFields(Context $context): void
    {
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        if ($this->getCustomFieldSetIds($context)->getTotal() > 0) {
            return;
        }

        $customFieldSetRepository->create([$this->getCustomFieldsConfiguration()], $context);
    }

    public function getCustomFieldSetIds(Context $context): IdSearchResult
    {
        return $this->container->get('custom_field_set.repository')->searchIds(
            (new Criteria())->addFilter(
                new EqualsFilter('name', self::CUSTOM_FIELD_SET_PREFIX)
            ),
            $context
        );
    }

    public function getCustomFieldsConfiguration(): array
    {
        return [
            'id' => self::CUSTOM_FIELD_SET_PREFIX_ID,
            'name' => self::CUSTOM_FIELD_SET_PREFIX,
            'config' => [
                'label' => [
                    'en-GB' => 'ERIVE.delivery',
                    'de-DE' => 'ERIVE.delivery',
                ],
                'translated' => true,
                'technical_name' => self::CUSTOM_FIELD_SET_PREFIX,
            ],
            'customFields' => [
                [
                    'id' => self::CUSTOM_FIELD_PARCEL_NUMBER_ID,
                    'name' => self::CUSTOM_FIELD_PARCEL_NUMBER,
                    'type' => CustomFieldTypes::TEXT,
                    'config' => [
                        'name' => self::CUSTOM_FIELD_PARCEL_NUMBER,
                        'type' => CustomFieldTypes::TEXT,
                        'customFieldType' => CustomFieldTypes::TEXT,
                        'label' => [
                            'en-GB' => 'Parcel ID',
                            'de-DE' => 'Paket ID',
                        ],
                        "customFieldPosition" => 1,
                    ],
                ],
                [
                    'id' => self::CUSTOM_FIELD_PARCEL_LABEL_URL_ID,
                    'name' => self::CUSTOM_FIELD_PARCEL_LABEL_URL,
                    'type' => CustomFieldTypes::TEXT,
                    'config' => [
                        'name' => self::CUSTOM_FIELD_PARCEL_LABEL_URL,
                        'type' => CustomFieldTypes::TEXT,
                        'customFieldType' => CustomFieldTypes::TEXT,
                        'label' => [
                            'en-GB' => 'Parcel Label URL',
                            'de-DE' => 'Paketlabel-URL',
                        ],
                        "customFieldPosition" => 2,
                    ],
                ],
            ],
            'relations' => [[
                'entityName' => 'order',
            ]],
        ];
    }
}
