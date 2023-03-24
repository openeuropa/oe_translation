<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Storage handler for the TranslationRequest entities.
 */
class TranslationRequestStorage extends SqlContentEntityStorage implements TranslationRequestStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function getTranslationRequestsForEntityRevision(ContentEntityInterface $entity, string $bundle): array {
    $ids = $this->getQuery()
      ->condition('content_entity.entity_id', $entity->id())
      ->condition('content_entity.entity_revision_id', $entity->getRevisionId())
      ->condition('content_entity.entity_type', $entity->getEntityTypeId())
      ->condition('bundle', $bundle)
      ->execute();

    if (!$ids) {
      return [];
    }

    return $this->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslationRequestsForEntity(ContentEntityInterface $entity, string $bundle): array {
    $ids = $this->getQuery()
      ->condition('content_entity.entity_id', $entity->id())
      ->condition('content_entity.entity_type', $entity->getEntityTypeId())
      ->condition('bundle', $bundle)
      ->execute();

    if (!$ids) {
      return [];
    }

    return $this->loadMultiple($ids);
  }

}
