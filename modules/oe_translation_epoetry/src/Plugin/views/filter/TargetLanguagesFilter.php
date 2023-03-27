<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry\Plugin\views\filter;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters by the target languages.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("oe_translation_epoetry_target_languages_filter")
 */
class TargetLanguagesFilter extends InOperator {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $languageManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    $languages = $this->languageManager->getLanguages();
    foreach ($languages as $language) {
      $this->valueOptions[$language->getId()] = $language->getName();
    }

    return $this->valueOptions;
  }

}
