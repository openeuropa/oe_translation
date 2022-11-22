<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_corporate_workflow;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Trait for helper function used in the corporate workflow translation context.
 */
trait CorporateWorkflowTranslationTrait {

  /**
   * Returns the human readable name of the entity version.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The version.
   *
   * @return string
   *   The version.
   */
  protected function getEntityVersion(ContentEntityInterface $entity): string {
    $entity_version_storage = $this->entityTypeManager->getStorage('entity_version_settings');
    $version_field_setting = $entity_version_storage->load($entity->getEntityTypeId() . '.' . $entity->bundle());
    if (!$version_field_setting) {
      return '';
    }
    $version_field = $version_field_setting->getTargetField();
    $version = $entity->get($version_field)->getValue();
    $version = reset($version);
    return implode('.', $version);
  }

}
