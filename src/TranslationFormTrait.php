<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Element;
use Drupal\filter\Entity\FilterFormat;
use Drupal\oe_translation\Form\TranslationRequestForm;

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
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected function translationFormElement(array $data, array $existing_translation_data, bool $disable) {
    $element = [];

    foreach (Element::children($data) as $key) {
      if (!isset($data[$key]['#text']) || !TranslationSourceHelper::filterData($data[$key])) {
        continue;
      }

      // The char sequence '][' confuses the form API so we need to replace
      // it.
      $target_key = str_replace('][', '|', $key);
      $element[$target_key] = [
        '#tree' => TRUE,
        '#theme' => 'local_translation_form_element_group',
        '#ajaxid' => Html::getUniqueId('oe-translation-local-element-' . $key),
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

      if (!empty($data[$key]['#max_length'])) {
        $element[$target_key]['translation']['#max_length'] = $data[$key]['#max_length'];
        $element[$target_key]['translation']['#element_validate'] = [
          [TranslationRequestForm::class, 'validateMaxLength'],
        ];
      }

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

  /**
   * Generates the embedded paragraph names.
   *
   * When we are dealing with paragraphs, we want to include the name of the
   * paragraph type in the label of the field.
   *
   * @param array $data
   *   The data.
   *
   * @return array
   *   The data.
   */
  protected function generateParagraphFieldName(array $data): array {
    foreach (Element::children($data) as $child_key) {
      $child = &$data[$child_key];
      if (!isset($child['entity'])) {
        continue;
      }

      $entity_type = $child['entity']['#entity_type'];
      if ($entity_type !== 'paragraph') {
        continue;
      }

      $bundle = $child['entity']['#entity_bundle'];
      $bundle_type = $this->entityTypeManager->getDefinition($entity_type)->getBundleEntityType();
      $label = $this->entityTypeManager->getStorage($bundle_type)->load($bundle)->label();
      // If we have a label already, it means we have more than 1 cardinality
      // and the label is like so: Delta #0. In this case, we make the label
      // like so: (0) Paragraph type. If we don't have a #label, it means it's
      // single cardinality, in which case we just use the paragraph type label.
      $child['#label'] = isset($child['#label']) ? ' (' . $child_key . ') / ' . $label : $label;

      foreach (Element::children($child['entity']) as $sub_child_key) {
        $sub_child = &$child['entity'][$sub_child_key];
        $sub_child = $this->generateParagraphFieldName($sub_child);
      }
    }

    return $data;
  }

}
