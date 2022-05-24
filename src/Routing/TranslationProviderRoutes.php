<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Routing;

use Drupal\oe_translation_poetry\Plugin\tmgmt\Translator\PoetryTranslator;
use Drupal\tmgmt\TranslatorManager;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provider of routes defined by the translation provider plugins.
 *
 * @deprecated
 */
class TranslationProviderRoutes {

  /**
   * The translator manager.
   *
   * @var \Drupal\tmgmt\TranslatorManager
   */
  protected $translatorManager;

  /**
   * TranslationProviderRoutes constructor.
   *
   * @param \Drupal\tmgmt\TranslatorManager $translatorManager
   *   The translator manager.
   */
  public function __construct(TranslatorManager $translatorManager) {
    $this->translatorManager = $translatorManager;
  }

  /**
   * Provides the routes.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   The collection of routes.
   */
  public function routes(): RouteCollection {
    $collection = new RouteCollection();
    $definitions = $this->translatorManager->getDefinitions();
    foreach ($definitions as $plugin_id => $definition) {
      $plugin = $this->translatorManager->createInstance($plugin_id);
      if (!$plugin instanceof PoetryTranslator) {
        continue;
      }

      $collection->addCollection($plugin->getRoutes());
    }

    return $collection;
  }

}
