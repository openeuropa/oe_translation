<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Controller;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\content_translation\Controller\ContentTranslationController as BaseContentTranslationController;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
   * A map of language names to IDs.
   *
   * @var array
   */
  protected $languageMap = [];

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
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(ContentTranslationManagerInterface $manager, EntityTypeManagerInterface $entity_type_manager, TranslatorManager $translator_manager, EventDispatcherInterface $event_dispatcher, LanguageManagerInterface $language_manager = NULL) {
    $entity_field_manager = \Drupal::service('entity_field.manager');
    
    parent::__construct($manager, $entity_field_manager);

    $this->entityTypeManager = $entity_type_manager;
    $this->translatorManager = $translator_manager;
    $this->eventDispatcher = $event_dispatcher;
    if (!$language_manager instanceof LanguageManagerInterface) {
      $language_manager = \Drupal::languageManager();
    }
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('content_translation.manager'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.tmgmt.translator'),
      $container->get('event_dispatcher'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function overview(RouteMatchInterface $route_match, $entity_type_id = NULL): array {
    $build = parent::overview($route_match, $entity_type_id);

    $handler = $this->entityTypeManager->getHandler($entity_type_id, 'oe_translation');
    $supported_translators = $handler->getSupportedTranslators();

    foreach ($build['content_translation_overview']['#rows'] as $row_key => &$row) {
      end($row);
      $pos = key($row);
      if (!isset($row[$pos]['data']['#links'])) {
        continue;
      }

      // Set a hreflang on the first column based on the target translation
      // language.
      $language = $this->getLanguageFromRow($row);
      if ($language) {
        $row[0] = [
          'data' => $row[0],
        ];

        $row[0]['hreflang'] = $language;
      }

      // If there are no supported translators, this is where we stop.
      if (empty($supported_translators)) {
        continue;
      }

      // Remove all the default operation links.
      foreach ($row[$pos]['data']['#links'] as $link_key => $link) {
        unset($build['content_translation_overview']['#rows'][$row_key][$pos]['data']['#links'][$link_key]);
      }
    }

    if (empty($supported_translators)) {
      // If there are no supported translators for this entity type, we do
      // not call any translator plugins to override the page.
      return $build;
    }

    /** @var \Drupal\tmgmt\TranslatorInterface[] $translators */
    $translators = $this->entityTypeManager->getStorage('tmgmt_translator')->loadMultiple();
    foreach ($translators as $translator) {
      $plugin_id = $translator->getPluginId();
      if (!in_array($plugin_id, $supported_translators)) {
        // If this translator is not supported, we don't override the overview
        // page.
        continue;
      }

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

  /**
   * Returns a language code from the row.
   *
   * @param array $row
   *   The row.
   *
   * @return string
   *   The language.
   */
  protected function getLanguageFromRow(array $row):? string {
    if (!$this->languageMap) {
      $languages = $this->languageManager->getLanguages();
      foreach ($languages as $language) {
        $this->languageMap[$language->getName()] = $language->getId();
      }
    }

    $name = $row[0];
    if ($name instanceof TranslatableMarkup) {
      // The original language is not printed as the language name but in the
      // format: English (Original language).
      $name = $name->getArguments()['@language_name'];
    }

    return $this->languageMap[$name] ?? NULL;
  }

}
