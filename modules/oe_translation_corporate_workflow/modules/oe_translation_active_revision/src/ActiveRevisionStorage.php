<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_active_revision;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\oe_translation_active_revision\Plugin\Field\FieldType\LanguageWithEntityRevisionItem;

/**
 * Defines the storage handler class for Active Revision entities..
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
  public function getActiveRevisionForEntity(string $entity_type, string $entity_id, string $entity_revision_id = NULL): ?ActiveRevisionInterface {
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
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity revision.
   */
  public function getLangcodeMappedRevision(string $langcode, ContentEntityInterface $entity, CacheableMetadata $cache): ?ContentEntityInterface {
    $active_revision = $this->getActiveRevisionForEntity($entity->getEntityTypeId(), $entity->id());
    if (!$active_revision instanceof ActiveRevisionInterface) {
      $cache->addCacheTags(['oe_translation_active_revision_list']);
      return NULL;
    }

    $cache->addCacheableDependency($active_revision);
    $is_validated = $entity->get('moderation_state')->value === 'validated';

    $language_revisions = $active_revision->get('field_language_revision')->getValue();
    foreach ($language_revisions as $delta => $values) {
      if ($values['langcode'] === $langcode) {
        if ($is_validated && (int) $values['scope'] === LanguageWithEntityRevisionItem::SCOPE_PUBLISHED) {
          // We don't want to use the mapping if the scope is restricted to
          // the current published version and the current entity for which
          // we are finding the map is validated (the next version).
          return NULL;
        }

        return $this->entityTypeManager->getStorage('node')->loadRevision($values['entity_revision_id']);
      }
    }

    return NULL;
  }

}
