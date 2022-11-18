<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Element;
use Drupal\filter\Entity\FilterFormat;

/**
 * Helpful methods for dealing with translation forms.
 */
trait TranslationFormTrait {

  /**
   * Renders a form element for each piece of translatable data.
   *
   * @param array $data
   *   The data.
   * @param array $existing_translation_data
   *   Existing entity translation data.
   * @param bool $disable
   *   Whether to disable all translation elements.
   *
   * @return array
   *   The form element.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  protected function translationFormElement(array $data, array $existing_translation_data, bool $disable) {
    $element = [];

    foreach (Element::children($data) as $key) {
      if (!isset($data[$key]['#text']) || !$this->tmgmtData->filterData($data[$key])) {
        continue;
      }

      // The char sequence '][' confuses the form API so we need to replace
      // it.
      $target_key = str_replace('][', '|', $key);
      $element[$target_key] = [
        '#tree' => TRUE,
        '#theme' => 'local_translation_form_element_group',
        '#ajaxid' => Html::getUniqueId('tmgmt-local-element-' . $key),
        '#parent_label' => $data[$key]['#parent_label'],
        '#zebra' => '',
      ];

      // Manage the height of the texteareas, depending on the length of the
      // description. The minimum number of rows is 3 and the maximum is 15.
      $rows = ceil(strlen($data[$key]['#text']) / 100);
      if ($rows < 3) {
        $rows = 3;
      }
      elseif ($rows > 15) {
        $rows = 15;
      }
      $element[$target_key]['source'] = [
        '#type' => 'textarea',
        '#value' => $data[$key]['#text'],
        '#title' => $this->t('Source'),
        '#disabled' => TRUE,
        '#rows' => $rows,
      ];

      if (empty($existing_translation_data)) {
        // If we don't have existing translation data already passed, we
        // default to whatever there is in the original data. If the original
        // data doesn't have translation values, we fill them in with the
        // source.
        $translation_value = $data[$key]['#translation']['#text'] ?? $data[$key]['#text'];
      }
      else {
        // Otherwise, we fish it out from the source values of the
        // existing translation data.
        $translation_value = $existing_translation_data[$key]['#text'] ?? NULL;
      }

      $element[$target_key]['translation'] = [
        '#type' => 'textarea',
        '#default_value' => $translation_value,
        '#title' => $this->t('Translation'),
        '#rows' => $rows,
        '#allow_focus' => !$disable,
        '#disabled' => $disable,
      ];

      // Check if the field has a text format and ensure the element uses one
      // too (if the user has access to it).
      $format_id = $data[$key]['#format'] ?? NULL;
      if (!$format_id) {
        continue;
      }

      /** @var \Drupal\filter\Entity\FilterFormat $format */
      $format = FilterFormat::load($format_id);

      if ($format && $format->access('use')) {
        // In case a user has permission to translate the content using
        // selected text format, add a format id into the list of allowed
        // text formats. Otherwise, no text format will be used.
        $element[$target_key]['source']['#allowed_formats'] = [$format_id];
        $element[$target_key]['translation']['#allowed_formats'] = [$format_id];
        $element[$target_key]['source']['#type'] = 'text_format';
        $element[$target_key]['translation']['#type'] = 'text_format';
      }
    }

    return $element;
  }

}
