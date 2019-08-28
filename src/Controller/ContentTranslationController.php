<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Controller;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\content_translation\Controller\ContentTranslationController as BaseContentTranslationController;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\oe_translation\AlterableTranslatorInterface;
use Drupal\oe_translation\Event\ContentTranslationOverviewAlterEvent;
use Drupal\tmgmt\TranslatorManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Initializes the content translation controller.
   *
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $manager
   *   A content translation manager instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\tmgmt\TranslatorManager $translator_manager
   *   The translation manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(ContentTranslationManagerInterface $manager, EntityTypeManagerInterface $entity_type_manager, TranslatorManager $translator_manager, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($manager);

    $this->entityTypeManager = $entity_type_manager;
    $this->translatorManager = $translator_manager;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('content_translation.manager'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.tmgmt.translator'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function overview(RouteMatchInterface $route_match, $entity_type_id = NULL): array {
    $build = parent::overview($route_match, $entity_type_id);

    // Remove all the default operation links.
    foreach ($build['content_translation_overview']['#rows'] as $row_key => &$row) {
      end($row);
      $pos = key($row);
      if (!isset($row[$pos]['data']['#links'])) {
        continue;
      }

      foreach ($row[$pos]['data']['#links'] as $link_key => $link) {
        unset($build['content_translation_overview']['#rows'][$row_key][$pos]['data']['#links'][$link_key]);
      }
    }

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

    $entity_type_id = $entity_type_id ?? '';
    $event = new ContentTranslationOverviewAlterEvent($build, $route_match, $entity_type_id);
    $this->eventDispatcher->dispatch(ContentTranslationOverviewAlterEvent::NAME, $event);

    return $event->getBuild();
  }

}
