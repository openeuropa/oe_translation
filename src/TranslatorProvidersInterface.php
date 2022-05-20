<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Interface for Translator providers service.
 */
interface TranslatorProvidersInterface {

  /**
   * Determines whether the entity type has a local translator plugin.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return bool
   *   TRUE if it has a local translator, FALSE otherwise.
   */
  public function hasLocal(EntityTypeInterface $entity_type): bool;

  /**
   * Determines whether the entity type has any remote translator plugins.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return bool
   *   TRUE if it has at least a remote translator, FALSE otherwise.
   */
  public function hasRemote(EntityTypeInterface $entity_type): bool;

  /**
   * Retrieves the remote translator plugins, if any.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array
   *   Array containing the remote plugins.
   */
  public function getRemotePlugins(EntityTypeInterface $entity_type): array;

}
