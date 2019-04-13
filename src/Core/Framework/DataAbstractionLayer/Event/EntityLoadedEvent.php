<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class EntityLoadedEvent extends NestedEvent
{
    /**
     * @var Entity[]
     */
    protected $entities;

    /**
     * @var EntityDefinition
     */
    protected $definition;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var bool
     */
    protected $nested = true;

    public function __construct(EntityDefinition $definition, array $entities, Context $context, bool $nested = true)
    {
        $this->entities = $entities;
        $this->definition = $definition;
        $this->context = $context;
        $this->name = $this->definition->getEntityName() . '.loaded';
        $this->nested = $nested;
    }

    public function getEntities(): array
    {
        return $this->entities;
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEvents(): ?NestedEventCollection
    {
        if (!$this->nested) {
            return null;
        }

        // @todo@jp fix data format
        [$definitions, $associations] = $this->extractAssociations($this->definition, $this->entities);

        $events = [];

        foreach ($associations as $definitionClass => $entities) {
            $events[] = $this->createNested($definitions[$definitionClass], $entities);
        }

        return new NestedEventCollection($events);
    }

    protected function extractAssociations(EntityDefinition $definition, iterable $entities): array
    {
        $associations = $definition->getFields();

        $events = [];
        $definitions = [];
        /** @var Field $association */
        foreach ($associations as $association) {
            if (!$association instanceof AssociationField) {
                continue;
            }

            $isExtension = $association->is(Extension::class);

            if ($association instanceof ManyToOneAssociationField || $association instanceof OneToOneAssociationField) {
                /** @var Entity $entity */
                foreach ($entities as $entity) {
                    try {
                        if ($isExtension) {
                            $reference = $entity->getExtension($association->getPropertyName());
                        } else {
                            $reference = $entity->get($association->getPropertyName());
                        }
                    } catch (\InvalidArgumentException $e) {
                        continue;
                    }

                    if ($reference) {
                        $events[$association->getReferenceDefinition()->getClass()][] = $reference;
                        $definitions[$association->getReferenceDefinition()->getClass()] = $association->getReferenceDefinition();
                    }
                }

                continue;
            }

            $referenceDefinition = $association->getReferenceDefinition();
            if ($association instanceof ManyToManyAssociationField) {
                $referenceDefinition = $association->getToManyReferenceDefinition();
            }

            foreach ($entities as $entity) {
                try {
                    if ($isExtension) {
                        $references = $entity->getExtension($association->getPropertyName());
                    } else {
                        $references = $entity->get($association->getPropertyName());
                    }
                } catch (\InvalidArgumentException $e) {
                    continue;
                }

                if (empty($references)) {
                    continue;
                }

                foreach ($references as $reference) {
                    $events[$referenceDefinition->getClass()][] = $reference;
                }
                $definitions[$referenceDefinition->getClass()] = $referenceDefinition;
            }
        }

        $recursive = [];
        foreach ($events as $nestedDefinition => $nested) {
            /*
             * contains now an array of arrays
             *
             * [
             *      [
             *          ProductManufacturerDefinition => [$entity,$entity,$entity,$entity,$entity],
             *          ProductPriceDefinition => [$entity,$entity,$entity,$entity,$entity]
             *      ]
             * ]
             */
            [$associationDefinitions, $associationEvents] = $this->extractAssociations($definitions[$nestedDefinition], $nested);

            $recursive[] = $associationEvents;
            $definitions = array_merge($definitions, $associationDefinitions);
        }

        foreach ($recursive as $nested) {
            if (empty($nested)) {
                continue;
            }
            //iterate nested array of definitions and entities and merge them into root $events
            foreach ($nested as $nestedDefinition => $nestedEntities) {
                foreach ($nestedEntities as $nestedEntity) {
                    $events[$nestedDefinition][] = $nestedEntity;
                }
            }
        }

        return [$definitions, $events];
    }

    protected function createNested(EntityDefinition $definition, array $entities): EntityLoadedEvent
    {
        return new EntityLoadedEvent($definition, $entities, $this->context, false);
    }
}
