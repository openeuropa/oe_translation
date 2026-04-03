<?php

declare(strict_types=1);

namespace Drupal\oe_translation_corporate_workflow;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Trait for helper function used in the corporate workflow translation context.
 */
trait CorporateWorkflowTranslationTrait {

  /**
   * Static cache for the value of the field name representing the version.
   *
   * @var array
   */
  protected $versionFieldStaticCache = [];

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
    $cid = $entity->getEntityTypeId() . '.' . $entity->bundle();
    if (isset($this->versionFieldStaticCache[$cid])) {
      $version_field = $this->versionFieldStaticCache[$cid];
    }
    else {
      $entity_version_storage = $this->entityTypeManager->getStorage('entity_version_settings');
      $version_field_setting = $entity_version_storage->load($entity->getEntityTypeId() . '.' . $entity->bundle());
      if (!$version_field_setting) {
        return '';
      }

      $version_field = $version_field_setting->getTargetField();
    }

    if (!$version_field) {
      return '';
    }

    $this->versionFieldStaticCache[$cid] = $version_field;

    $version = $entity->get($version_field)->getValue();
    $version = reset($version);
    return implode('.', $version);
  }

  /**
   * Query for the revisions in the same major and minor.
   *
   * Given a revision, this is meant to return the published revision and the
   * validated one from the same major and minor version.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  protected function queryRevisionsInSameMajorAndMinor(ContentEntityInterface $entity): array {
    $cid = $entity->getEntityTypeId() . '.' . $entity->bundle();
    if (!isset($this->versionFieldStaticCache[$cid])) {
      // Ensure the version field is resolved and cached.
      $this->getEntityVersion($entity);
    }
    $version_field = $this->versionFieldStaticCache[$cid] ?? NULL;
    if (!$version_field) {
      return [];
    }

    /** @var \Drupal\entity_version\Plugin\Field\FieldType\EntityVersionItem $original_version */
    $original_version = $entity->get($version_field)->first();
    $original_major = $original_version->get('major')->getValue();
    $original_minor = $original_version->get('minor')->getValue();
    return $this->entityTypeManager->getStorage($entity->getEntityTypeId())
      ->getQuery()
      ->condition($entity->getEntityType()->getKey('id'), $entity->id())
      ->condition($version_field . '.major', $original_major)
      ->condition($version_field . '.minor', $original_minor)
      ->accessCheck(FALSE)
      ->allRevisions()
      ->execute();
  }

}
