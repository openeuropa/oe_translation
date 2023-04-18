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
   *   The translated data array.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $langcode
   *   The langcode into which we save the data.
   * @param bool $save
   *   Whether to call save on the entity.
   * @param array $original_data
   *   The original translation data array. This is used when it can contain
   *   extra information which is used in the saving of the translation.
   *
   * @return bool
   *   TRUE for success, FALSE if not.
   */
  public function saveData(array $data, ContentEntityInterface $entity, string $langcode, bool $save = TRUE, array $original_data = []): bool;

  /**
   * Returns fields that should be embedded into the data for the given entity.
   *
   * Includes explicitly enabled fields and composite entities that are
   * implicitly included to the translatable data.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to get the translatable data from.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   A list of field definitions that can be embedded.
   */
  public function getEmbeddableFields(ContentEntityInterface $entity): array;

}
