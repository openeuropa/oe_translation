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
  public function query(): void {
    // Leave empty to avoid a query on this field.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): string|MarkupInterface {
    /** @var \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $request */
    $request = $this->getEntity($values);
    $target_languages = $request->getTargetLanguages();
    $total = count($target_languages);
    $grouped = [];
    foreach ($target_languages as $language_with_status) {
      $status = $language_with_status->getStatus();
      $langcode = $language_with_status->getLangcode();
      $grouped[$status][$langcode] = $this->languageManager->getLanguage($langcode)?->getName() ?? $langcode;
    }

    $this->sortGroupedStatuses($grouped);

    $translated_count = $this->getCountOfTranslatedLanguages($grouped, $request);

    $items = [];
    foreach ($grouped as $status => $languages) {
      $items[] = new FormattableMarkup('<strong>@status</strong>: @languages', [
        '@status' => $status,
        '@languages' => implode(', ', $languages),
      ]);
    }

    $elements = [
      'tooltip' => [
        '#theme' => 'tooltip',
        '#label' => new FormattableMarkup('@total / @synced', [
          '@total' => $total,
          '@synced' => $translated_count,
        ]),
        '#text' => [
          '#theme' => 'item_list',
          '#items' => $items,
        ],
      ],
    ];
    return $this->getRenderer()->render($elements);
  }

  /**
   * Counts the translated languages.
   *
   * These include the ones in Review, the ones that have been locally
   * accepted and the ones that have been synced.
   *
   * @param array $grouped
   *   The grouped languages by status.
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $request
   *   The request.
   *
   * @return int
   *   The count.
   */
  protected function getCountOfTranslatedLanguages(array $grouped, TranslationRequestCdtInterface $request): int {
    $count = 0;
    // Include the languages that are in Review.
    if (isset($grouped[TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW])) {
      $count += count($grouped[TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW]);
    }

    // Include the synced ones.
    if (isset($grouped[TranslationRequestRemoteInterface::STATUS_LANGUAGE_SYNCHRONISED])) {
      $count += count($grouped[TranslationRequestRemoteInterface::STATUS_LANGUAGE_SYNCHRONISED]);
    }

    // Include also the languages which have been accepted locally, but only
    // if there is translation data.
    if (!isset($grouped[TranslationRequestRemoteInterface::STATUS_LANGUAGE_ACCEPTED])) {
      return $count;
    }

    foreach ($grouped[TranslationRequestRemoteInterface::STATUS_LANGUAGE_ACCEPTED] as $langcode => $name) {
      if (!$this->hasLanguageArrived($request, $langcode)) {
        continue;
      }

      $count++;
    }

    return $count;
  }

  /**
   * Checks whether a given translation has arrived from CDT.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $request
   *   The request.
   * @param string $language
   *   The language.
   *
   * @return bool
   *   Whether a given translation has arrived from CDT.
   */
  protected function hasLanguageArrived(TranslationRequestCdtInterface $request, string $language): bool {
    $data = $request->getTranslatedData();
    return isset($data[$language]);
  }

  /**
   * Sorts statuses based on a logical order.
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

    uksort($grouped, function ($a, $b) use ($order) {
      $a_key = array_search($a, $order);
      $b_key = array_search($b, $order);

      if ($a_key == $b_key) {
        return 0;
      }

      return ($a_key < $b_key) ? -1 : 1;
    });
  }

}
