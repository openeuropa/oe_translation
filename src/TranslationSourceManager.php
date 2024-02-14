<?php

declare(strict_types=1);

namespace Drupal\oe_translation;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityReference;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation\Event\TranslationSourceEvent;
use Drupal\oe_translation\TranslationSourceFieldProcessor\TranslationSourceFieldProcessorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Manages the reading and writing of the translation data from/to entities.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class TranslationSourceManager implements TranslationSourceManagerInterface {

  /**
   * The entity revision info service.
   *
   * @var \Drupal\oe_translation\EntityRevisionInfoInterface
   */
  protected $entityRevisionInfo;

  /**
   * The content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface
   */
  protected $contentTranslationManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypePluginManager;

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * TranslationSourceManager constructor.
   *
   * @param \Drupal\oe_translation\EntityRevisionInfoInterface $entityRevisionInfo
   *   The entity revision info service.
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $contentTranslationManager
   *   The content translation manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $fieldTypePluginManager
   *   The field type plugin manager.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $classResolver
   *   The class resolver.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(EntityRevisionInfoInterface $entityRevisionInfo, ContentTranslationManagerInterface $contentTranslationManager, EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory, ModuleHandlerInterface $moduleHandler, FieldTypePluginManagerInterface $fieldTypePluginManager, ClassResolverInterface $classResolver, EventDispatcherInterface $eventDispatcher) {
    $this->entityRevisionInfo = $entityRevisionInfo;
    $this->contentTranslationManager = $contentTranslationManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $configFactory;
    $this->moduleHandler = $moduleHandler;
    $this->fieldTypePluginManager = $fieldTypePluginManager;
    $this->classResolver = $classResolver;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function extractData(ContentEntityInterface $entity): array {
    $field_definitions = $entity->getFieldDefinitions();
    $exclude_field_types = ['language'];
    $exclude_field_names = ['moderation_state'];

    $is_bundle_translatable = $this->contentTranslationManager->isEnabled($entity->getEntityTypeId(), $entity->bundle());

    // Exclude field types from translation.
    $translatable_fields = array_filter($field_definitions, function (FieldDefinitionInterface $field_definition) use ($exclude_field_types, $exclude_field_names, $is_bundle_translatable) {

      if ($is_bundle_translatable) {
        // Field is not translatable.
        if (!$field_definition->isTranslatable()) {
          return FALSE;
        }
      }
      elseif (!$field_definition->getFieldStorageDefinition()->isTranslatable()) {
        return FALSE;
      }

      // Field type matches field types to exclude.
      if (in_array($field_definition->getType(), $exclude_field_types)) {
        return FALSE;
      }

      // Field name matches field names to exclude.
      if (in_array($field_definition->getName(), $exclude_field_names)) {
        return FALSE;
      }

      // User marked the field to be excluded.
      if ($field_definition instanceof ThirdPartySettingsInterface) {
        $is_excluded = $field_definition->getThirdPartySetting('oe_translation', 'translation_source_excluded', FALSE);
        if ($is_excluded) {
          return FALSE;
        }
      }
      return TRUE;
    });

    $this->moduleHandler->alter('oe_translation_source_translatable_fields', $entity, $translatable_fields);

    $data = [];
    foreach ($translatable_fields as $field_name => $field_definition) {
      $field = $entity->get($field_name);
      $data[$field_name] = $this->getFieldProcessor($field_definition->getType())->extractTranslatableData($field);
    }

    $embeddable_fields = $this->getEmbeddableFields($entity);
    foreach ($embeddable_fields as $field_name => $field_definition) {
      $field = $entity->get($field_name);

      /** @var \Drupal\Core\Field\FieldItemInterface $field_item */
      foreach ($field as $delta => $field_item) {
        foreach ($field_item->getProperties(TRUE) as $property_key => $property) {
          // If the property is a content entity reference and it's value is
          // defined, than we call this method again to get all the data.
          if ($property instanceof EntityReference && $property->getValue() instanceof ContentEntityInterface) {
            // All the labels are here, to make sure we don't have empty
            // labels in the UI because of no data.
            $data[$field_name]['#label'] = $field_definition->getLabel();
            if (count($field) > 1) {
              // More than one item, add a label for the delta.
              $data[$field_name][$delta]['#label'] = t('Delta #@delta', ['@delta' => $delta]);
            }
            // Get the referenced entity.
            $referenced_entity = $property->getValue();
            // Get the source language code.
            $langcode = $entity->language()->getId();
            // If the referenced entity is translatable and has a translation
            // use it instead of the default entity translation.
            if ($this->contentTranslationManager->isEnabled($referenced_entity->getEntityTypeId(), $referenced_entity->bundle()) && $referenced_entity->hasTranslation($langcode)) {
              $referenced_entity = $referenced_entity->getTranslation($langcode);
            }
            $data[$field_name][$delta][$property_key] = $this->extractData($referenced_entity);
            // Use the ID of the entity to identify it later, do not rely on the
            // UUID as content entities are not required to have one.
            $data[$field_name][$delta][$property_key]['#id'] = $property->getValue()->id();
          }
        }

      }
    }

    $this->sortData($data, $entity);

    // Add information about the entity type and bundle.
    $data['#entity_type'] = $entity->getEntityTypeId();
    $data['#entity_bundle'] = $entity->bundle();

    $event = new TranslationSourceEvent($entity, $data, $entity->language()->getId());
    $this->eventDispatcher->dispatch($event, TranslationSourceEvent::EXTRACT);
    return $event->getData();
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function saveData(array $data, ContentEntityInterface $entity, string $langcode, bool $save = TRUE, array $original_data = []): ContentEntityInterface {
    // Use the entity revision info service to resolve the correct revision
    // onto which to save the translation.
    $entity = $this->entityRevisionInfo->getEntityRevision($entity, $langcode);

    if (!$entity->hasTranslation($langcode)) {
      // We need to ensure that after we create the translation, we maintain
      // the "default revision" flag so that in case we are creating a
      // translation for a non-default revision, it doesn't transform it by
      // accident into a default revision. This happens, for example, if we
      // have a published revision followed by a draft (default) revision as a
      // result of an Unpublishing action.
      $default_revision = $entity->isDefaultRevision();
      $entity->addTranslation($langcode, $entity->toArray());
      $entity->isDefaultRevision($default_revision);
    }

    $event = new TranslationSourceEvent($entity, $data, $langcode, $save);
    $event->setOriginalData($original_data);
    $this->eventDispatcher->dispatch($event, TranslationSourceEvent::SAVE);
    $data = $event->getData();

    $translation = $entity->getTranslation($langcode);
    if ($this->contentTranslationManager->isEnabled($translation->getEntityTypeId(), $translation->bundle())) {
      $this->contentTranslationManager->getTranslationMetadata($translation)->setSource($entity->language()->getId());
    }

    foreach (Element::children($data) as $field_name) {
      $field_data = $data[$field_name];

      if (!$translation->hasField($field_name)) {
        throw new \Exception("Field '$field_name' does not exist on entity " . $translation->getEntityTypeId() . '/' . $translation->id());
      }

      $field = $translation->get($field_name);
      $field_processor = $this->getFieldProcessor($field->getFieldDefinition()->getType());
      if (!$field_data && $entity->get($field_name)->isEmpty()) {
        // If there is no field data for this field, it means the data was
        // removed in the source and the new translation request no longer
        // includes it. In this case we need to remove the value also from the
        // translation or the user will not be able to ever remove it.
        $field->setValue([]);
        continue;
      }
      $field_processor->setTranslations($field_data, $field);
    }

    $embeddable_fields = $this->getEmbeddableFields($entity);
    foreach ($embeddable_fields as $field_name => $field_definition) {

      if (!isset($data[$field_name])) {
        continue;
      }

      $field = $translation->get($field_name);
      $target_type = $field->getFieldDefinition()->getFieldStorageDefinition()->getSetting('target_type');
      $is_target_type_translatable = $this->contentTranslationManager->isEnabled($target_type);
      // In case the target type is not translatable, the referenced entity will
      // be duplicated. As a consequence, remove all the field items from the
      // translation, update the field value to use the field object from the
      // source language.
      if (!$is_target_type_translatable) {
        $field = clone $entity->get($field_name);

        if (!$translation->get($field_name)->isEmpty()) {
          $translation->set($field_name, NULL);
        }
      }

      foreach (Element::children($data[$field_name]) as $delta) {
        $field_item = $data[$field_name][$delta];
        foreach (Element::children($field_item) as $property) {
          // Find the referenced entity. In case we are dealing with
          // untranslatable target types, the source entity will be returned.
          if ($target_entity = $this->findReferencedEntity($field, $field_item, $delta, $property, $is_target_type_translatable)) {
            if ($is_target_type_translatable) {
              // If the field is an embeddable reference and the property is a
              // content entity, process it recursively.
              $this->saveData($field_item[$property], $target_entity, $langcode, $save);
            }
            else {
              $duplicate = $this->createTranslationDuplicate($target_entity, $langcode);
              // Do not save the duplicate as it's going to be saved with the
              // main entity.
              $this->saveData($duplicate, $field_item[$property], $langcode, FALSE);
              $translation->get($field_name)->set($delta, $duplicate);
            }
          }
        }
      }
    }

    if ($save) {
      if ($translation->hasField('translation_request') && isset($data['#translation_request']) && $data['#translation_request'] instanceof TranslationRequestInterface) {
        $translation_request = $data['#translation_request'];
        $translation->set('translation_request', $translation_request->id());
      }
      $translation->save();
    }

    return $translation;
  }

  /**
   * {@inheritdoc}
   */
  public function getEmbeddableFields(ContentEntityInterface $entity): array {
    // Get the configurable embeddable references.
    $field_definitions = $entity->getFieldDefinitions();
    $embeddable_field_names = $this->configFactory->get('oe_translation.settings')->get('translation_source_embedded_fields');
    $embeddable_fields = array_filter($field_definitions, function (FieldDefinitionInterface $field_definition) use ($embeddable_field_names) {
      return isset($embeddable_field_names[$field_definition->getTargetEntityTypeId()][$field_definition->getName()]);
    });

    // Get always embedded references.
    foreach ($field_definitions as $field_name => $field_definition) {
      $storage_definition = $field_definition->getFieldStorageDefinition();

      $property_definitions = $storage_definition->getPropertyDefinitions();
      foreach ($property_definitions as $property_definition) {
        // Look for entity_reference properties where the storage definition
        // has a target type setting.
        if (in_array($property_definition->getDataType(), [
          'entity_reference',
          'entity_revision_reference',
        ]) && ($target_type_id = $storage_definition->getSetting('target_type'))) {
          $is_target_type_enabled = $this->contentTranslationManager->isEnabled($target_type_id);
          $target_entity_type = $this->entityTypeManager->getDefinition($target_type_id);

          // Include current entity reference field that is considered a
          // composite and translatable or if the parent entity is considered a
          // composite as well. This allows to embed nested untranslatable
          // fields (For example: Paragraphs).
          if ($target_entity_type->get('entity_revision_parent_type_field') && ($is_target_type_enabled || $entity->getEntityType()->get('entity_revision_parent_type_field'))) {
            $embeddable_fields[$field_name] = $field_definition;
          }
        }
      }
    }

    return $embeddable_fields;
  }

  /**
   * Tries to find the referenced entity from a field list.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field list.
   * @param array $field_item
   *   The field item data array.
   * @param int $delta
   *   The delta.
   * @param string $property
   *   The field property.
   * @param bool $is_target_type_translatable
   *   (optional) Whether the target entity type is translatable.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity if found.
   */
  protected function findReferencedEntity(FieldItemListInterface $field, array $field_item, int $delta, string $property, $is_target_type_translatable = TRUE): ?ContentEntityInterface {
    // If an id is provided, loop over the field item deltas until we find the
    // matching entity. In case of untranslatable target types return the
    // source target entity as it will be duplicated.
    if (isset($field_item[$property]['#id'])) {
      foreach ($field as $item_delta => $item) {
        if ($item->$property instanceof ContentEntityInterface) {
          /** @var \Drupal\Core\Entity\ContentEntityInterface $referenced_entity */
          $referenced_entity = $item->$property;
          if ($referenced_entity->id() == $field_item[$property]['#id'] || ($item_delta === $delta && !$is_target_type_translatable)) {
            return $referenced_entity;
          }
        }
      }
    }
    // For backwards compatiblity, also support matching based on the delta.
    elseif ($field->offsetExists($delta) && $field->offsetGet($delta)->$property instanceof ContentEntityInterface) {
      return $field->offsetGet($delta)->$property;
    }

    return NULL;
  }

  /**
   * Creates a translation duplicate of the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $target_entity
   *   The target entity to clone.
   * @param string $langcode
   *   Language code for all the clone entities created.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   New entity object with the data from the original entity. Not
   *   saved. No sub-entities are cloned.
   */
  protected function createTranslationDuplicate(ContentEntityInterface $target_entity, $langcode): ContentEntityInterface {
    $duplicate = $target_entity->createDuplicate();

    // Change the original language.
    if ($duplicate->getEntityType()->hasKey('langcode')) {
      $duplicate->set($duplicate->getEntityType()->getKey('langcode'), $langcode);
    }

    return $duplicate;
  }

  /**
   * Returns the field processor for a given field type.
   *
   * @param string $field_type
   *   The field type.
   *
   * @return \Drupal\oe_translation\TranslationSourceFieldProcessor\TranslationSourceFieldProcessorInterface
   *   The field processor for this field type.
   */
  protected function getFieldProcessor($field_type): TranslationSourceFieldProcessorInterface {
    $definition = $this->fieldTypePluginManager->getDefinition($field_type);
    return $this->classResolver->getInstanceFromDefinition($definition['oe_translation_source_field_processor']);
  }

  /**
   * Sorts the extracted data by the weights in the form display.
   *
   * @param array $data
   *   The data.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected function sortData(array &$data, ContentEntityInterface $entity): void {
    $entity_form_display = $this->entityTypeManager->getStorage('entity_form_display')->load($entity->getEntityTypeId() . '.' . $entity->bundle() . '.default');
    if (!$entity_form_display) {
      return;
    }
    uksort($data, function ($a, $b) use ($entity_form_display) {
      $a_weight = NULL;
      $b_weight = NULL;
      // Get the weights.
      if ($entity_form_display->getComponent($a) && (isset($entity_form_display->getComponent($a)['weight']) && !is_null($entity_form_display->getComponent($a)['weight']))) {
        $a_weight = (int) $entity_form_display->getComponent($a)['weight'];
      }
      if ($entity_form_display->getComponent($b) && (isset($entity_form_display->getComponent($b)['weight']) && !is_null($entity_form_display->getComponent($b)['weight']))) {
        $b_weight = (int) $entity_form_display->getComponent($b)['weight'];
      }

      // If neither field has a weight, sort alphabetically.
      if ($a_weight === NULL && $b_weight === NULL) {
        return ($a > $b) ? 1 : -1;
      }
      // If one of them has no weight, the other comes first.
      elseif ($a_weight === NULL) {
        return 1;
      }
      elseif ($b_weight === NULL) {
        return -1;
      }
      // If both have a weight, sort by weight.
      elseif ($a_weight == $b_weight) {
        return 0;
      }
      else {
        return ($a_weight > $b_weight) ? 1 : -1;
      }
    });
  }

}
