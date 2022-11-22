<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Event class used for determining the entity revision to use.
 *
 * This revision is used to save the translation data onto.
 *
 * @see \Drupal\oe_translation\EntityRevisionInfo
 */
class EntityRevisionEvent extends Event {

  const EVENT = 'oe_translation.entity_revision_event';

  /**
   * The entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The target langcode.
   *
   * @var string
   */
  protected $targetLangcode;

  /**
   * Constructs a EntityRevisionEvent.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $target_langcode
   *   The target langcode.
   */
  public function __construct(ContentEntityInterface $entity, string $target_langcode) {
    $this->entity = $entity;
    $this->targetLangcode = $target_langcode;
  }

  /**
   * Returns the entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Sets the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function setEntity(ContentEntityInterface $entity): void {
    $this->entity = $entity;
  }

  /**
   * Returns the target langcode.
   *
   * @return string
   *   The langcode.
   */
  public function getTargetLanguage(): string {
    return $this->targetLangcode;
  }

}
