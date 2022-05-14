<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_block_field;

use Drupal\block_field\BlockFieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tmgmt_content\DefaultFieldProcessor;

/**
 * Field processor for the Block reference field.
 */
class BlockFieldProcessor extends DefaultFieldProcessor {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function extractTranslatableData(FieldItemListInterface $field) {
    $data = [];
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_item */
    $field_definition = $field->getFieldDefinition();

    foreach ($field as $delta => $field_item) {
      $property = $field_item->getProperties()['settings'];
      $data['#label'] = $field_definition->getLabel();

      if (count($field) > 1) {
        // More than one item, add a label for the delta.
        $data[$delta]['#label'] = $this->t('Delta #@delta', ['@delta' => $delta]);
      }
      $data[$delta]['settings__label'] = [
        '#label' => $this->t('Block title'),
        '#text' => $property->getValue()['label'],
        '#translate' => TRUE,
        '#max_length' => 255,
      ];
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function setTranslations($field_data, FieldItemListInterface $field) {
    foreach (Element::children($field_data) as $delta) {
      $field_item = $field_data[$delta];
      $property_data = $field_item['settings__label'];

      // If there is translation data for the field property, save it.
      if (isset($property_data['#translation']['#text']) && $property_data['#translate']) {
        // If the offset does not exist, populate it with the current value
        // from the source content, so that the translated field offset can be
        // saved.
        if (!$field->offsetExists(($delta))) {
          $translation = $field->getEntity();
          $source = $translation->getUntranslated();
          $source_field = $source->get($field->getName());
          $source_offset = $source_field->offsetGet($delta);
          // Note that the source language value will be immediately
          // overwritten.
          $field->offsetSet($delta, $source_offset);
        }

        $this->setTranslatedTitle($field->offsetGet($delta), $property_data['#translation']['#text']);
      }
    }
  }

  /**
   * Sets the translated title value onto the block field settings.
   *
   * @param \Drupal\block_field\BlockFieldItemInterface $field_item
   *   The block field item.
   * @param string $title
   *   The title value to save.
   */
  protected function setTranslatedTitle(BlockFieldItemInterface $field_item, string $title): void {
    $settings = $field_item->getValue()['settings'];
    $settings['label'] = $title;
    $field_item->set('settings', $settings);
  }

}
