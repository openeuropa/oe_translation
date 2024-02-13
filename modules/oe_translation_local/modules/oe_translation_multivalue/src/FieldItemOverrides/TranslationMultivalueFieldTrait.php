<?php

declare(strict_types=1);

namespace Drupal\oe_translation_multivalue\FieldItemOverrides;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Trait for handling all the logic needed in the field item classes.
 */
trait TranslationMultivalueFieldTrait {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'translation_multivalue' => FALSE,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $elements = [];
    $elements['translation_multivalue'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Translation multivalue'),
      '#default_value' => $this->getSetting('translation_multivalue'),
      '#description' => $this->t('Whether to keep a translation ID of each value in the field to aid in local translations.'),
      '#disabled' => $has_data,
    ];

    return $elements + parent::storageSettingsForm($form, $form_state, $has_data);
  }

  /**
   * Overrides the property definitions.
   */
  protected static function overridePropertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $enabled = (bool) $field_definition->getSetting('translation_multivalue');

    if ((int) $field_definition->getCardinality() === 1 || !$enabled) {
      return $properties;
    }

    $properties['translation_id'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Translation ID'));

    return $properties;
  }

  /**
   * Overrides the schema.
   */
  protected static function overrideSchema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    $enabled = (bool) $field_definition->getSetting('translation_multivalue');

    if ((int) $field_definition->getCardinality() === 1 || !$enabled) {
      return $schema;
    }

    $schema['columns']['translation_id'] = [
      'description' => 'The translation ID.',
      'type' => 'varchar',
      'length' => 128,
      'unique keys' => [
        'translation_id' => 'translation_id',
      ],
      'not null' => FALSE,
    ];

    return $schema;
  }

}
