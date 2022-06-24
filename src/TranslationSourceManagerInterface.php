<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Extracts and saves translation date from/onto entities.
 */
interface TranslationSourceManagerInterface {

  /**
   * Extracts the data.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The translation source data.
   */
  public function extractData(ContentEntityInterface $entity): array;

  /**
   * Saves the data.
   *
   * @param array $data
   *   The data array.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $langcode
   *   The langcode into which we save the data.
   *
   * @return bool
   *   TRUE for success, FALSE if not.
   */
  public function saveData(array $data, ContentEntityInterface $entity, string $langcode): bool;

}
