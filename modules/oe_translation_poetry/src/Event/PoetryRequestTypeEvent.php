<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event class for determining what kind of Poetry request can be made.
 */
class PoetryRequestTypeEvent extends Event {

  const EVENT = 'oe_translation_poetry.request_type_event';

  /**
   * The entity being translated.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The request type constant.
   *
   * @var string
   */
  protected $requestType;

  /**
   * The job information of the current request.
   *
   * @var array
   */
  protected $jobInfo;

  /**
   * PoetryRequestTypeEvent constructor.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being translated.
   * @param string $requestType
   *   The request type constant.
   * @param object|null $jobInfo
   *   The job information of the current request.
   */
  public function __construct(ContentEntityInterface $entity, string $requestType, $jobInfo = NULL) {
    $this->entity = $entity;
    $this->requestType = $requestType;
    $this->jobInfo = $jobInfo;
  }

  /**
   * Returns the entity being translated.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Returns the request type constant.
   *
   * @return string
   *   The request type.
   */
  public function getRequestType(): string {
    return $this->requestType;
  }

  /**
   * Sets the request type constant.
   *
   * @param string $requestType
   *   The request type.
   */
  public function setRequestType(string $requestType): void {
    $this->requestType = $requestType;
  }

  /**
   * Returns the job info.
   *
   * @return object|null
   *   The job info.
   */
  public function getJobInfo(): ?\stdClass {
    return $this->jobInfo;
  }

}
