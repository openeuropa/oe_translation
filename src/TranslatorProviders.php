<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Checks if an entity type is exposed to any translator provider.
 */
class TranslatorProviders implements TranslatorProvidersInterface {

  /**
   * {@inheritdoc}
   */
  public function hasLocal(EntityTypeInterface $entity_type): bool {
    $translators = $this->getTranslators($entity_type);
    if (!$translators) {
      return FALSE;
    }
    return $translators['local'];
  }

  /**
   * {@inheritdoc}
   */
  public function hasRemote(EntityTypeInterface $entity_type): bool {
    $translators = $this->getTranslators($entity_type);
    if (!$translators) {
      return FALSE;
    }
    return (bool) $translators['remote'];
  }

  /**
   * {@inheritdoc}
   */
  public function getRemotePlugins(EntityTypeInterface $entity_type): ?array {
    if (!$this->hasRemote($entity_type)) {
      return NULL;
    }
    $translators = $this->getTranslators($entity_type);
    return $translators['remote'];
  }

  /**
   * Retrieves the entity type oe_translation_translators configuration.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return array|bool
   *   Array containing the configuration, FALSE if there is none.
   */
  protected function getTranslators(EntityTypeInterface $entity_type): ?array {
    $additional = $entity_type->get('additional');
    if (!isset($additional['oe_translation_translators'])) {
      return FALSE;
    }
    return $additional['oe_translation_translators'];
  }

}
