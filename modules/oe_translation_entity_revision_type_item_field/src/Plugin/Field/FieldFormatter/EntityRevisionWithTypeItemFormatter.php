<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_entity_revision_type_item_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\oe_translation_entity_revision_type_item_field\Plugin\Field\FieldType\EntityRevisionWithTypeItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the EntityRevisionWithTypeItem formatter.
 *
 * @FieldFormatter(
 *   id = "oe_translation_entity_revision_type_item_formatter",
 *   label = @Translation("Entity revision with type item formatter"),
 *   field_types = {
 *     "oe_translation_entity_revision_type_item"
 *   }
 * )
 */
class EntityRevisionWithTypeItemFormatter extends FormatterBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a EntityRevisionWithTypeItemFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    if (count($items) === 0) {
      return [];
    }

    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[$delta] = $this->viewElement($item);
    }

    return $elements;
  }

  /**
   * Default formatter view for a single item.
   *
   * @param \Drupal\oe_translation_entity_revision_type_item_field\Plugin\Field\FieldType\EntityRevisionWithTypeItem $item
   *  The field item.
   *
   * @return array
   *   The single item element.
   */
  protected function viewElement(EntityRevisionWithTypeItem $item): array {
    $default = [
      '#markup' => implode('-', [$item->entity_type, $item->entity_id, $item->entity_revision_id]),
    ];

    $storage = $this->entityTypeManager->getStorage($item->entity_type);
    $entity = $storage->loadRevision($item->entity_revision_id);
    if (!$entity instanceof EntityInterface) {
      return $default;
    }

    return $entity->getEntityType()->hasKey('label') ? ['#markup' => $entity->label()] : $default;
  }

}
