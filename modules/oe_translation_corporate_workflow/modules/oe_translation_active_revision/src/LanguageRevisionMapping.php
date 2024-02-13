<?php

declare(strict_types=1);

namespace Drupal\oe_translation_active_revision;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\oe_translation_active_revision\Plugin\Field\FieldType\LanguageWithEntityRevisionItem;

/**
 * Value object holding language-revision mapping information.
 */
class LanguageRevisionMapping {

  /**
   * The langcode.
   *
   * @var string
   */
  protected $langcode;

  /**
   * The entity revision, if mapped.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface|null
   */
  protected $entity = NULL;

  /**
   * Whether there is a mapping but to nothing.
   *
   * It means the entity should show no translation, even if there is one in
   * the system.
   *
   * @var bool
   */
  protected $mappedToNull = FALSE;

  /**
   * The mapping scope.
   *
   * @var int
   */
  protected $scope = LanguageWithEntityRevisionItem::SCOPE_BOTH;

  /**
   * Constructs a LanguageRevisionMapping.
   *
   * @param string $langcode
   *   The langcode.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   The entity revision, if mapped.
   */
  public function __construct(string $langcode, ContentEntityInterface $entity = NULL) {
    $this->langcode = $langcode;
    $this->entity = $entity;
  }

  /**
   * Returns te entity revision, if mapped.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity revision, if mapped.
   */
  public function getEntity(): ?ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Checks whether the entity is mapped to something.
   *
   * @return bool
   *   Whether the entity is mapped to something.
   */
  public function isMapped(): bool {
    return $this->entity instanceof ContentEntityInterface || $this->isMappedToNull();
  }

  /**
   * Whether it is mapped to null.
   *
   * @return bool
   *   Whether it is mapped to null.
   */
  public function isMappedToNull(): bool {
    return $this->mappedToNull;
  }

  /**
   * Sets to map to null.
   *
   * @param bool $mappedToNull
   *   The value.
   */
  public function setMappedToNull(bool $mappedToNull): void {
    $this->mappedToNull = $mappedToNull;
  }

  /**
   * Returns the mapping scope.
   *
   * @return int
   *   The mapping scope.
   */
  public function getScope(): int {
    return $this->scope;
  }

  /**
   * Sets the mapping scope.
   *
   * @param int $scope
   *   The mapping scope.
   */
  public function setScope(int $scope): void {
    $this->scope = $scope;
  }

}
