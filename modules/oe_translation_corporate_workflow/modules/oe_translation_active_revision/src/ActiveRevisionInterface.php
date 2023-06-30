<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_active_revision;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\oe_translation_active_revision\Plugin\Field\FieldType\LanguageWithEntityRevisionItem;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining an active revision entity type.
 */
interface ActiveRevisionInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Checks if there is a mapping for a given language.
   *
   * @param string $langcode
   *   The langcode.
   *
   * @return bool
   *   Whether there is a mapping.
   */
  public function hasLanguageMapping(string $langcode): bool;

  /**
   * Removes a given language mapping.
   *
   * @param string $langcode
   *   The langcode.
   *
   * @return ActiveRevisionInterface
   *   The current entity.
   */
  public function removeLanguageMapping(string $langcode): ActiveRevisionInterface;

  /**
   * Sets a given language mapping.
   *
   * @param string $langcode
   *   The langcode.
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_id
   *   The entity ID.
   * @param int $entity_revision_id
   *   The revision ID.
   * @param int $scope
   *   The mapping scope.
   *
   * @return ActiveRevisionInterface
   *   The current entity.
   */
  public function setLanguageMapping(string $langcode, string $entity_type, int $entity_id, int $entity_revision_id, int $scope = LanguageWithEntityRevisionItem::SCOPE_BOTH): ActiveRevisionInterface;

  /**
   * Updates the scope of a mapping.
   *
   * @param string $langcode
   *   The langcode.
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_id
   *   The entity ID.
   * @param int $scope
   *   The mapping scope.
   *
   * @return ActiveRevisionInterface
   *   The current entity.
   */
  public function updateMappingScope(string $langcode, string $entity_type, int $entity_id, int $scope): ActiveRevisionInterface;

  /**
   * Returns the language mapping for a given language.
   *
   * @param string $langcode
   *   The langcode.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The current entity for which we are determining the mapping.
   *
   * @return \Drupal\oe_translation_active_revision\LanguageRevisionMapping
   *   The language mapping.
   */
  public function getLanguageMapping(string $langcode, ContentEntityInterface $entity): LanguageRevisionMapping;

}
