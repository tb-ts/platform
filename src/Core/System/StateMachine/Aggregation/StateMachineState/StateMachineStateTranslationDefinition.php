<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class StateMachineStateTranslationDefinition extends EntityTranslationDefinition
{
    public function getEntityName(): string
    {
        return 'state_machine_state_translation';
    }

    public function getEntityClass(): string
    {
        return StateMachineStateTranslationEntity::class;
    }

    protected function getParentDefinitionClass(): string
    {
        return StateMachineStateDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->setFlags(new Required()),
            new CustomFields(),
        ]);
    }
}
