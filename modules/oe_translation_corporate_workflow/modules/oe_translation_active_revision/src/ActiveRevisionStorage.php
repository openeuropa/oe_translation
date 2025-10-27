<?php

declare(strict_types=1);

namespace Drupal\oe_translation_active_revision;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Defines the storage handler class for Active Revision entities.
 */
class ActiveRevisionStorage extends SqlContentEntityStorage {

  /**
   * Returns the active revision for a given entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $entity_id
   *   The entity ID.
   * @param string|null $entity_revision_id
   *   The optional entity revision ID.
   *
   * @return ActiveRevisionInterface|null
   *   The active revision entity if found.
   */
  public function getActiveRevisionForEntity(string $entity_type, string $entity_id, ?string $entity_revision_id = NULL): ?ActiveRevisionInterface {
    $query = \Drupal::entityTypeManager()
      ->getStorage('oe_translation_active_revision')
      ->getQuery()
      ->condition('field_language_revision.entity_type', $entity_type)
      ->condition('field_language_revision.entity_id', $entity_id);

    if ($entity_revision_id) {
      $query->condition('field_language_revision.entity_revision_id', $entity_revision_id);
    }

    $ids = $query
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();

    if (!$ids) {
      return NULL;
    }
    $id = reset($ids);

    return $this->load($id);
  }

  /**
   * Tries to load the entity revision mapped to this langcode.
   *
   * @param string $langcode
   *   The langcode.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Cache metadata to bubble up.
   *
   * @return \Drupal\oe_translation_active_revision\LanguageRevisionMapping
   *   The language revision mapping.
   */
  public function getLangcodeMapping(string $langcode, ContentEntityInterface $entity, CacheableMetadata $cache): LanguageRevisionMapping {
    $active_revision = $this->getActiveRevisionForEntity($entity->getEntityTypeId(), $entity->id());
    if (!$active_revision instanceof ActiveRevisionInterface) {
      $cache->addCacheTags(['oe_translation_active_revision_list']);
      return new LanguageRevisionMapping($langcode, NULL);
    }

    $cache->addCacheableDependency($active_revision);
    return $active_revision->getLanguageMapping($langcode, $entity);
  }

}
