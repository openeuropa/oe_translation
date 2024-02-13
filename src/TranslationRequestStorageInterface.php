<?php

declare(strict_types=1);

namespace Drupal\oe_translation;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Translation request storage interface.
 */
interface TranslationRequestStorageInterface {

  /**
   * Returns translation requests for a given entity revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $bundle
   *   The bundle.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface[]
   *   The translation requests.
   */
  public function getTranslationRequestsForEntityRevision(ContentEntityInterface $entity, string $bundle): array;

  /**
   * Returns translation requests for a given entity.
   *
   * It returns all the translation requests, regardless of the revisions.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $bundle
   *   The bundle.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface[]
   *   The translation requests.
   */
  public function getTranslationRequestsForEntity(ContentEntityInterface $entity, string $bundle): array;

}
