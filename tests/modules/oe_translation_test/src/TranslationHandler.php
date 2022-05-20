<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_test;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\oe_translation\OeTranslationHandler;
use Drupal\tmgmt\TranslatorManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Test translation handler.
 *
 * @deprecated
 */
class TranslationHandler extends OeTranslationHandler {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * TranslationHandler constructor.
   *
   * @param \Drupal\tmgmt\TranslatorManager $translatorManager
   *   The translator manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   */
  public function __construct(TranslatorManager $translatorManager, EntityTypeManagerInterface $entityTypeManager, StateInterface $state, EntityTypeInterface $entity_type) {
    parent::__construct($translatorManager, $entityTypeManager, $entity_type);
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('plugin.manager.tmgmt.translator'),
      $container->get('entity_type.manager'),
      $container->get('state'),
      $entity_type
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTranslators(): array {
    $supported = parent::getSupportedTranslators();

    $enabled_entity_types = $this->state->get('oe_translation_test_enabled_translators', []);
    if (in_array($this->entityType->id(), $enabled_entity_types)) {
      // Enable one of the translators for the entity type.
      $supported[] = 'permission';
    }

    return $supported;
  }

}
