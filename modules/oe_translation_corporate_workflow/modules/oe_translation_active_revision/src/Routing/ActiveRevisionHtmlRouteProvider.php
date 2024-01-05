<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_active_revision\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;

/**
 * Provides HTML routes for entities with administrative pages.
 */
class ActiveRevisionHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getCanonicalRoute(EntityTypeInterface $entity_type) {
    return $this->getEditFormRoute($entity_type);
  }

}
