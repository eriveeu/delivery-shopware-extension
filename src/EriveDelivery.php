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
    public const CUSTOM_FIELD_ANNOUNCE_ON_DELIVERY = self::CUSTOM_FIELD_SET_PREFIX . "_announceOnDelivery";

    public function install(InstallContext $context): void
    {
        parent::install($context);
        $this->createCustomFields($context->getContext());
    }

    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);

        if ($context->keepUserData()) {
            return;
        }

        // TODO: remove custom fields
    }


    public function createCustomFields(Context $context): void
    {
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');
        $customFields = $customFieldSetRepository->searchIds((new Criteria())
            ->addFilter(
                new EqualsFilter(
                    'name',
                    EriveDelivery::CUSTOM_FIELD_SET_PREFIX
                )
            ), $context);

        // custom fields are already created
        if ($customFields->getTotal() > 0) {
            return;
        }

        $customFieldSetRepository->upsert([$this->getCustomFieldsConfiguration()], $context);
    }

    public function getCustomFieldsConfiguration(): array
    {
        return [
            'id' => EriveDelivery::CUSTOM_FIELD_SET_PREFIX_ID,
            'name' => EriveDelivery::CUSTOM_FIELD_SET_PREFIX,
            'config' => [
                'label' => [
                    'en-GB' => 'ERIVE.delivery',
                    'de-DE' => 'ERIVE.delivery',
                ],
                'translated' => true,
                'technical_name' => EriveDelivery::CUSTOM_FIELD_SET_PREFIX,
            ],
            'customFields' => [
                [
                    'id' => EriveDelivery::CUSTOM_FIELD_PARCEL_NUMBER_ID,
                    'name' => EriveDelivery::CUSTOM_FIELD_PARCEL_NUMBER,
                    'type' => CustomFieldTypes::TEXT,
                    'config' => [
                        'name' => EriveDelivery::CUSTOM_FIELD_PARCEL_NUMBER,
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
                    'id' => EriveDelivery::CUSTOM_FIELD_PARCEL_LABEL_URL_ID,
                    'name' => EriveDelivery::CUSTOM_FIELD_PARCEL_LABEL_URL,
                    'type' => CustomFieldTypes::TEXT,
                    'config' => [
                        'name' => EriveDelivery::CUSTOM_FIELD_PARCEL_LABEL_URL,
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
