<?php

declare(strict_types=1);

namespace Drupal\oe_translation_remote;

use Drupal\Core\Form\FormStateInterface;

/**
 * Used by remote translation providers that pick a language.
 *
 * It renders a list of language checkboxes and does validation and processing
 * of the selected values.
 */
trait LanguageCheckboxesAwareTrait {

  /**
   * Adds the language checkboxes to the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $languages
   *   The language objects.
   */
  protected function addLanguageCheckboxes(array &$form, FormStateInterface $form_state, array $languages = []): void {
    $languages = !empty($languages) ? $languages : $this->languageManager->getLanguages();
    $entity = $this->getEntity();
    $source_language = $entity->language()->getId();
    unset($languages[$source_language]);

    $form['languages'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Languages'),
      '#attributes' => ['class' => ['js-language-checkboxes-wrapper']],
    ];
    $form['languages']['all'] = [
      '#type' => 'checkbox',
      '#title' => '<strong>' . t('Select all') . '</strong>',
      '#attributes' => ['class' => ['js-checkbox-all']],
    ];

    $label_map = [
      'eu' => $this->t('EU Languages'),
      'non_eu' => $this->t('Non-EU Languages'),
    ];

    $grouped_languages = $this->getGroupedLanguages($languages);
    foreach ($grouped_languages as $category => $category_languages) {
      $form['languages'][$category]["{$category}_heading"] = [
        '#markup' => '<h2>' . $label_map[$category] . '</h2>',
      ];

      $form['languages'][$category] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['languages-wrapper']],
      ];

      $form['languages'][$category]['all'] = [
        '#type' => 'checkbox',
        '#title' => '<strong>' . t('Select all @category', ['@category' => $label_map[$category]]) . '</strong>',
        '#attributes' => ['class' => ["js-checkbox-$category-all"]],
      ];

      $form['languages'][$category]['languages'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['languages']],
      ];
      foreach ($category_languages as $language) {
        $form['languages'][$category]['languages'][$language->getId()] = [
          '#type' => 'checkbox',
          '#title' => $language->getName(),
          '#attributes' => [
            'class' => [
              "js-checkbox-$category-language",
            ],
          ],
        ];
      }
    }

    $form['#attached']['library'][] = 'oe_translation_remote/language_checkboxes';
  }

  /**
   * Returns the submitted languages from the form state.
   *
   * Each language gets the
   * TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED
   * status on it by default.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The submitted languages.
   */
  protected function getSubmittedLanguages(array &$form, FormStateInterface $form_state): array {
    $language_values = $form_state->getValue('languages');
    $languages = [];
    foreach ($language_values as $langcode => $value) {
      if ($value === 1 && $langcode !== 'all') {
        $languages[] = [
          'langcode' => $langcode,
          'status' => TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED,
        ];
      }
    }

    return $languages;
  }

  /**
   * Returns all the languages available for configuration.
   *
   * The languages are grouped by the category they belong to. For example,
   * "eu" or "non_eu".
   *
   * @return array
   *   The languages.
   */
  protected function getGroupedLanguages(array $languages): array {
    /** @var \Drupal\language\ConfigurableLanguageInterface[] $languages */
    $languages = $this->entityTypeManager->getStorage('configurable_language')->loadMultiple(array_keys($languages));
    $grouped = [
      'eu' => [],
      'non_eu' => [],
    ];
    foreach ($languages as $code => $language) {
      $category = $language->getThirdPartySetting('oe_multilingual', 'category');
      if (!$category || !isset($grouped[$category])) {
        continue;
      }
      $grouped[$category][$code] = $language;
    }

    return array_filter($grouped, function ($languages) {
      return !empty($languages);
    });
  }

}
