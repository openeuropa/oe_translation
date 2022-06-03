<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_local\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Event used for altering the local translation overview list page.
 */
class TranslationLocalControllerAlterEvent extends Event {

  const NAME = 'oe_translation_local.translation_local_overview_alter';

  /**
   * The build array.
   *
   * @var array
   */
  protected $build;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The entity type ID.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * TranslationLocalControllerAlterEvent constructor.
   *
   * @param array $build
   *   The build array.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   * @param string $entity_type_id
   *   The entity type ID.
   */
  public function __construct(array $build, RouteMatchInterface $routeMatch, string $entity_type_id) {
    $this->build = $build;
    $this->routeMatch = $routeMatch;
    $this->entityTypeId = $entity_type_id;
  }

  /**
   * Gets the build array.
   *
   * @return array
   *   The build array.
   */
  public function getBuild(): array {
    return $this->build;
  }

  /**
   * Sets the build array.
   *
   * @param array $build
   *   The build array.
   */
  public function setBuild(array $build): void {
    $this->build = $build;
  }

  /**
   * Gets the route match.
   *
   * @return \Drupal\Core\Routing\RouteMatchInterface
   *   The route match.
   */
  public function getRouteMatch(): RouteMatchInterface {
    return $this->routeMatch;
  }

  /**
   * Gets the entity type ID.
   *
   * @return string
   *   The entity type ID.
   */
  public function getEntityTypeId(): string {
    return $this->entityTypeId;
  }

}
