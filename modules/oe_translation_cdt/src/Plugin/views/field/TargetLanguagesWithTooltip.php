<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Plugin\views\field;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\oe_translation_cdt\TranslationRequestCdtInterface;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to show the target languages with more information.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("oe_translation_cdt_target_languages_with_tooltip")
 */
class TargetLanguagesWithTooltip extends FieldPluginBase {

  /**
   * Creates a new TargetLanguagesWithTooltip object.
   *
   * @param array $configuration
   *   The configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected LanguageManagerInterface $languageManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
  public function query(): void {
    // Leave empty to avoid performing a query on this field.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string|MarkupInterface {
    /** @var \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $request */
    $request = $this->getEntity($values);
    $target_languages = $request->getTargetLanguages();
    $total_count = count($target_languages);
    $grouped = $this->groupLanguagesByStatus($target_languages);
    $this->sortGroupedStatuses($grouped);

    // Prepare the items for the tooltip.
    $items = [];
    foreach ($grouped as $status => $languages) {
      $items[] = new FormattableMarkup('<strong>@status</strong>: @languages', [
        '@status' => $status,
        '@languages' => implode(', ', $languages),
      ]);
    }

    // Count the number of translated languages.
    $translated_count = 0;
    foreach ($target_languages as $language_with_status) {
      if (isset($request->getTranslatedData()[$language_with_status->getLangcode()])) {
        $translated_count++;
      }
    }

    $elements = [
      'tooltip' => [
        '#theme' => 'tooltip',
        '#label' => new FormattableMarkup('@synced / @total', [
          '@total' => $total_count,
          '@synced' => $translated_count,
        ]),
        '#text' => [
          '#theme' => 'item_list',
          '#items' => $items,
        ],
      ],
    ];

    return $this->getRenderer()->renderPlain($elements);
  }

  /**
   * Groups the target languages by status.
   *
   * @param array $target_languages
   *   The target languages.
   *
   * @return array
   *   The associative array of grouped languages.
   */
  protected function groupLanguagesByStatus(array $target_languages): array {
    $grouped = [];
    foreach ($target_languages as $language_with_status) {
      $status = $language_with_status->getStatus();
      $langcode = $language_with_status->getLangcode();
      $label = $this->languageManager->getLanguage($langcode) ? $this->languageManager->getLanguage($langcode)->getName() : $langcode;
      $grouped[$status][$langcode] = $label;
    }
    return $grouped;
  }

  /**
   * Sorts statuses based on a predefined order.
   *
   * @param array $grouped
   *   The grouped languages by status.
   */
  protected function sortGroupedStatuses(array &$grouped): void {
    $order = [
      TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED,
      TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW,
      TranslationRequestRemoteInterface::STATUS_LANGUAGE_SYNCHRONISED,
      TranslationRequestCdtInterface::STATUS_LANGUAGE_CANCELLED,
      TranslationRequestCdtInterface::STATUS_LANGUAGE_FAILED,
    ];

    uksort($grouped, fn($a, $b) => array_search($a, $order) <=> array_search($b, $order));
  }

}
