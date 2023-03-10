<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_multivalue\Form;

use Drupal\Core\Render\Element;
use Drupal\oe_translation_local\Form\LocalTranslationRequestForm as LocalTranslationRequestFormOriginal;
use Drupal\oe_translation_local\TranslationRequestLocal;

/**
 * Form handler for the translation request entity add/edit forms.
 */
class LocalTranslationRequestForm extends LocalTranslationRequestFormOriginal {

  protected function getExistingTranslationData(TranslationRequestLocal $translation_request, string $langcode): array {
    $existing_translation_data = parent::getExistingTranslationData($translation_request, $langcode);
    if (!$existing_translation_data) {
      return $existing_translation_data;
    }

    // Reorder the existing translation data deltas.
    $data = $translation_request->getData();
    foreach (Element::children($data) as $key) {
      if (!isset($existing_translation_data[$key])) {
        continue;
      }

      $this->processTranslationData($data, $existing_translation_data, $translation_request->getContentEntity()->getEntityTypeId());

    }

    return $existing_translation_data;

  }

  protected function processTranslationData(array $data, array &$existing_translation_data, string $entity_type_id) {
    foreach (Element::children($data) as $key) {
      if (!isset($existing_translation_data[$key])) {
        continue;
      }

      $child = $data[$key];
      foreach (Element::children($child) as $child_key) {
        $sub_child = $child[$child_key];
        if (isset($sub_child['entity'])) {
          $this->processTranslationData($sub_child['entity'], $existing_translation_data[$key][$child_key]['entity'], $sub_child['entity']['#entity_type']);
        }
      }

      if ($this->hasTranslationId($key, $entity_type_id)) {
        $existing_translation_data_source = $existing_translation_data[$key];
        if (!$existing_translation_data_source) {
          continue;
        }
        foreach (Element::children($data[$key]) as $delta) {
          $translation_id = $data[$key][$delta]['translation_id']['#text'];
          $existing_translation_data[$key][$delta] = $this->getDeltaForTranslationId($translation_id, $existing_translation_data_source);
        }
      }
    }
  }

  protected function getDeltaForTranslationId($translation_id, $existing_translation_data) {
    foreach (Element::children($existing_translation_data) as $delta) {
      if ($existing_translation_data[$delta]['translation_id']['#text'] === $translation_id) {
        return $existing_translation_data[$delta];
      }
    }
  }

  protected function hasTranslationId(string $field_name, string $entity_type_id) {
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id);
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
    $field_definition = $field_definitions[$field_name] ?? NULL;
    if (!$field_definition) {
      return FALSE;
    }

    $property_names = $field_definition->getPropertyNames();
    return in_array('translation_id', $property_names);
  }

}
