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
    foreach ($languages as $language) {
      $form['languages'][$language->getId()] = [
        '#type' => 'checkbox',
        '#title' => $language->getName(),
        '#attributes' => ['class' => ['js-checkbox-language']],
      ];
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

}
