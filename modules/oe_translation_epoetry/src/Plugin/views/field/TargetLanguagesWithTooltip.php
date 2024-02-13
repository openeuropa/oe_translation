<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry\Plugin\views\field;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to show the target languages with more information.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("oe_translation_epoetry_target_languages_with_tooltip")
 */
class TargetLanguagesWithTooltip extends FieldPluginBase {

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
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
    $request = $this->getEntity($values);
    $target_languages = $request->getTargetLanguages();
    $total = count($target_languages);
    $grouped = [];
    foreach ($target_languages as $language_with_status) {
      $status = $language_with_status->getStatus();
      if (!$this->hasLanguageArrived($request, $language_with_status->getLangcode())) {
        $status = $status . ' [in ePoetry]';
      }
      $grouped[$status][$language_with_status->getLangcode()] = $this->languageManager->getLanguage($language_with_status->getLangcode())->getName();
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

    return [
      '#theme' => 'tooltip',
      '#label' => new FormattableMarkup('@total / @synced', [
        '@total' => $total,
        '@synced' => $translated_count,
      ]),
      '#text' => [
        '#theme' => 'item_list',
        '#items' => $items,
      ],
    ];
  }

  /**
   * Counts the translated languages.
   *
   * These include the ones in Review, the ones that have been locally
   * accepted and the ones that have been synced.
   *
   * @param array $grouped
   *   The grouped languages by status.
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The request.
   *
   * @return int
   *   The count.
   */
  protected function getCountOfTranslatedLanguages(array $grouped, TranslationRequestEpoetryInterface $request): int {
    $count = 0;
    // Include the languages that are in Review.
    if (isset($grouped[TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REVIEW])) {
      $count += count($grouped[TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REVIEW]);
    }

    // Include the synced ones.
    if (isset($grouped[TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SYNCHRONISED])) {
      $count += count($grouped[TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SYNCHRONISED]);
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
   * Checks whether a given translation has arrived from ePoetry.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The request.
   * @param string $language
   *   The language.
   *
   * @return bool
   *   Whether a given translation has arrived from ePoetry.
   */
  protected function hasLanguageArrived(TranslationRequestEpoetryInterface $request, string $language): bool {
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
      TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REQUESTED . ' [in ePoetry]',
      TranslationRequestEpoetryInterface::STATUS_LANGUAGE_ACCEPTED . ' [in ePoetry]',
      TranslationRequestEpoetryInterface::STATUS_LANGUAGE_ONGOING . ' [in ePoetry]',
      TranslationRequestEpoetryInterface::STATUS_LANGUAGE_READY . ' [in ePoetry]',
      TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SENT . ' [in ePoetry]',
      TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REVIEW,
      TranslationRequestEpoetryInterface::STATUS_LANGUAGE_ACCEPTED,
      TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SYNCHRONISED,
      TranslationRequestEpoetryInterface::STATUS_LANGUAGE_CLOSED . ' [in ePoetry]',
      TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SUSPENDED . ' [in ePoetry]',
      TranslationRequestEpoetryInterface::STATUS_LANGUAGE_CANCELLED . ' [in ePoetry]',
      TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REJECTED . ' [in ePoetry]',
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
