<?php

namespace NewMobilityEnterprise\Core\Checkout\Order;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;

class OrderCustomFields extends EntityExtension
{
  use EntityCustomFieldsTrait;
  public function extendFields(FieldCollection $collection): FieldCollection
  {
    return new FieldCollection([
      (new StringField('name', 'name')),
      (new StringField('description', 'description')),
      new CustomFields(),
    ]);
  }

  public function getDefinitionClass(): string
  {
    return ProductDefinition::class;
  }
}