<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Controller;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\content_translation\Controller\ContentTranslationController as BaseContentTranslationController;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\oe_translation\AlterableTranslatorInterface;
use Drupal\tmgmt\TranslatorManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Overrides the core content translation controller.
 *
 * Allows translator plugins to make alterations.
 */
class ContentTranslationController extends BaseContentTranslationController {

  /**
   * The translator manager.
   *
   * @var \Drupal\tmgmt\TranslatorManager
   */
  protected $translatorManager;

  /**
   * Initializes the content translation controller.
   *
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $manager
   *   A content translation manager instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\tmgmt\TranslatorManager $translator_manager
   *   The translation manager.
   */
  public function __construct(ContentTranslationManagerInterface $manager, EntityTypeManagerInterface $entity_type_manager, TranslatorManager $translator_manager) {
    parent::__construct($manager);

    $this->entityTypeManager = $entity_type_manager;
    $this->translatorManager = $translator_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('content_translation.manager'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.tmgmt.translator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function overview(RouteMatchInterface $route_match, $entity_type_id = NULL): array {
    $build = parent::overview($route_match, $entity_type_id);

    /** @var \Drupal\tmgmt\TranslatorInterface[] $translators */
    $translators = $this->entityTypeManager->getStorage('tmgmt_translator')->loadMultiple();
    foreach ($translators as $translator) {
      $plugin_id = $translator->getPluginId();
      try {
        $translator_plugin = $this->translatorManager->createInstance($plugin_id);
        if ($translator_plugin instanceof AlterableTranslatorInterface) {
          $translator_plugin->contentTranslationOverviewAlter($build, $route_match, $entity_type_id);
        }
      }
      catch (PluginNotFoundException $exception) {
        continue;
      }
    }

    return $build;
  }

}
