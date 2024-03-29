<?php

declare(strict_types=1);

namespace Drupal\oe_translation\TranslationSourceFieldProcessor;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\Type\StringInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default field processor applicable for most fields.
 */
class DefaultFieldProcessor implements TranslationSourceFieldProcessorInterface, ContainerInjectionInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a DefaultFieldProcessor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function extractTranslatableData(FieldItemListInterface $field): array {
    $data = [];
    /** @var \Drupal\Core\Field\FieldItemInterface $field_item */
    $field_definition = $field->getFieldDefinition();
    foreach ($field as $delta => $field_item) {
      $format = NULL;
      $translatable_properties = 0;

      foreach ($field_item->getProperties() as $property_key => $property) {

        // Ignore values that are not primitives.
        if (!($property instanceof PrimitiveInterface)) {
          continue;
        }

        $property_definition = $property->getDataDefinition();

        $translate = $this->shouldTranslateProperty($property);

        if ($field_definition instanceof ThirdPartySettingsInterface && $field_definition->isTranslatable() && $translation_sync = $field_definition->getThirdPartySetting('content_translation', 'translation_sync')) {
          $synced = array_keys(array_diff($translation_sync, array_filter($translation_sync)));
          if (in_array($property_key, $synced)) {
            $translate = FALSE;
          }
        }

        // All the labels are here, to make sure we don't have empty labels in
        // the UI because of no data.
        if ($translate === TRUE) {
          $data['#label'] = $field_definition->getLabel();
          if (count($field) > 1) {
            // More than one item, add a label for the delta.
            $data[$delta]['#label'] = t('Delta #@delta', ['@delta' => $delta]);
          }
        }

        $data[$delta][$property_key] = [
          '#label' => $property_definition->getLabel(),
          '#text' => $property->getValue(),
          '#translate' => $translate,
        ];

        $translatable_properties += (int) $translate;
        if ($translate && ($field_item->getFieldDefinition()->getFieldStorageDefinition()->getSetting('max_length') != 0)) {
          $data[$delta][$property_key]['#max_length'] = $field_item->getFieldDefinition()->getFieldStorageDefinition()->getSetting('max_length');
        }

        if ($property_definition->getDataType() == 'filter_format') {
          $format = $property->getValue();
        }
      }

      if (!empty($format)) {
        $data = $this->handleFormat($format, $data, $delta);
      }

      // If there is only one translatable property, remove the label for it.
      if ($translatable_properties <= 1 && !empty($data)) {
        foreach (Element::children($data[$delta]) as $property_key) {
          unset($data[$delta][$property_key]['#label']);
        }
      }
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function setTranslations($field_data, FieldItemListInterface $field): void {
    foreach (Element::children($field_data) as $delta) {
      $field_item = $field_data[$delta];
      foreach (Element::children($field_item) as $property) {
        $property_data = $field_item[$property];
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

          $field->offsetGet($delta)->set($property, $property_data['#translation']['#text']);
        }
      }
    }
  }

  /**
   * Returns whether the property should be translated or not.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $property
   *   The field property to check.
   *
   * @return bool
   *   TRUE if the property should be translated, FALSE otherwise.
   */
  protected function shouldTranslateProperty(TypedDataInterface $property): bool {
    // Ignore properties with limited allowed values or if they're not strings.
    if ($property instanceof OptionsProviderInterface || !($property instanceof StringInterface)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Handles adjusting the field item data if a format was detected.
   *
   * @param string $format
   *   The text format.
   * @param array $data
   *   The extracted field data.
   * @param int $delta
   *   The field item delta.
   *
   * @return array
   *   The adjusted field data.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected function handleFormat(string $format, array $data, int $delta): array {
    $allowed_formats = (array) $this->configFactory->get('oe_translation.settings')->get('translation_source_allowed_formats');
    if ($allowed_formats && array_search($format, $allowed_formats) === FALSE) {
      // There are allowed formats and this one is not part of them,
      // explicitly mark all data as untranslatable.
      foreach ($data[$delta] as $name => $value) {
        if (is_array($value) && isset($value['#translate'])) {
          $data[$delta][$name]['#translate'] = FALSE;
        }
      }
    }
    else {
      // Add the format to the translatable properties.
      foreach ($data[$delta] as $name => $value) {
        if (is_array($value) && isset($value['#translate']) && $value['#translate'] == TRUE) {
          $data[$delta][$name]['#format'] = $format;
        }
      }
    }

    return $data;
  }

}
