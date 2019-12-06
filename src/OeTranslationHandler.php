<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\tmgmt\TranslatorManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity handler for the OpenEuropa translation system.
 */
class OeTranslationHandler implements EntityHandlerInterface {

  /**
   * The translator manager.
   *
   * @var \Drupal\tmgmt\TranslatorManager
   */
  protected $translatorManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * OeTranslationHandler constructor.
   *
   * @param \Drupal\tmgmt\TranslatorManager $translatorManager
   *   The translator manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   */
  public function __construct(TranslatorManager $translatorManager, EntityTypeManagerInterface $entityTypeManager, EntityTypeInterface $entity_type) {
    $this->translatorManager = $translatorManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityType = $entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('plugin.manager.tmgmt.translator'),
      $container->get('entity_type.manager'),
      $entity_type
    );
  }

  /**
   * Returns the supported translators for this entity type.
   *
   * @return array
   *   The supported translators.
   */
  public function getSupportedTranslators(): array {
    $supported = [];
    /** @var \Drupal\tmgmt\TranslatorInterface[] $translators */
    $translators = $this->entityTypeManager->getStorage('tmgmt_translator')->loadMultiple();
    foreach ($translators as $translator) {
      $plugin_id = $translator->getPluginId();
      try {
        $translator_plugin = $this->translatorManager->createInstance($plugin_id);
        if (!$translator_plugin instanceof ApplicableTranslatorInterface) {
          continue;
        }

        if ($translator_plugin->applies($this->entityType)) {
          $supported[] = $plugin_id;
        }
      }
      catch (PluginNotFoundException $exception) {
        continue;
      }
    }

    return $supported;
  }

}
