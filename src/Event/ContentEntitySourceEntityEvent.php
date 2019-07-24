<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\tmgmt\JobItemInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event class used for determining the entity to use in the content source.
 *
 * @see \Drupal\oe_translation\ContentEntitySource
 */
class ContentEntitySourceEntityEvent extends Event {

  const EVENT = 'content_entity_source_entity_event';

  /**
   * The job item.
   *
   * @var \Drupal\tmgmt\JobItemInterface
   */
  protected $jobItem;

  /**
   * The entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * ContentEntitySourceEntityEvent constructor.
   *
   * @param \Drupal\tmgmt\JobItemInterface $jobItem
   *   The job item.
   */
  public function __construct(JobItemInterface $jobItem) {
    $this->jobItem = $jobItem;
  }

  /**
   * Returns the entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity.
   */
  public function getEntity(): ?ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Sets the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   The entity.
   */
  public function setEntity(ContentEntityInterface $entity): void {
    $this->entity = $entity;
  }

  /**
   * Returns the job item.
   *
   * @return \Drupal\tmgmt\JobItemInterface
   *   The job item.
   */
  public function getJobItem(): JobItemInterface {
    return $this->jobItem;
  }

}
