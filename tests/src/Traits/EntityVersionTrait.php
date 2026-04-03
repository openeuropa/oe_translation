<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation\Traits;

/**
 * Provides a method to install entity version fields with a custom name.
 */
trait EntityVersionTrait {

  /**
   * Installs an entity version field with a custom name on a bundle.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $bundle
   *   The bundle.
   * @param array $default_values
   *   The default values for the version field.
   * @param string $field_name
   *   The field name. Defaults to 'field_entity_version'.
   */
  protected function installEntityVersionField(string $entity_type, string $bundle, array $default_values = [], string $field_name = 'field_entity_version'): void {
    if (empty($default_values)) {
      $default_values = [
        'major' => 0,
        'minor' => 1,
        'patch' => 0,
      ];
    }

    $entity_type_manager = \Drupal::entityTypeManager();

    if (!$entity_type_manager->getStorage('field_storage_config')->load($entity_type . '.' . $field_name)) {
      $entity_type_manager->getStorage('field_storage_config')->create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'type' => 'entity_version',
      ])->save();
    }

    $entity_type_manager->getStorage('field_config')->create([
      'entity_type' => $entity_type,
      'field_name' => $field_name,
      'bundle' => $bundle,
      'label' => 'Version',
      'cardinality' => 1,
      'translatable' => FALSE,
      'default_value' => [$default_values],
    ])->save();

    $entity_type_manager->getStorage('entity_version_settings')->create([
      'target_entity_type_id' => $entity_type,
      'target_bundle' => $bundle,
      'target_field' => $field_name,
    ])->save();
  }

}
