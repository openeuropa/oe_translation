<?php

declare(strict_types=1);

namespace Drupal\oe_translation\TranslationSourceFieldProcessor;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\Core\TypedData\Plugin\DataType\Uri;
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

      // Prepare the source field value so we can use it in case we need to
      // unset some translation value.
      $translation = $field->getEntity();
      $source = $translation->getUntranslated();
      $source_field = $source->get($field->getName());
      $source_offset = $source_field->offsetGet($delta);

      foreach (Element::children($field_item) as $property) {
        $property_data = $field_item[$property];
        if (!isset($property_data['#translate']) || !$property_data['#translate']) {
          if ($field->getFieldDefinition()->getType() === 'link' && $field->offsetGet($delta) && $property === 'uri' && !UrlHelper::isExternal($property_data['#text'])) {
            // In case we are dealing with a link field URI column, we need to
            // check if the URI is not external because if it is, it is
            // marked as not translatable (see ::shouldTranslateProperty()).
            // In this case need to keep the col in sync with the source.
            $field->offsetGet($delta)->set($property, $source_offset->get($property)->getValue());
            continue;
          }

          if ($field->getFieldDefinition()->getType() === 'typed_link' && $field->offsetGet($delta) && $property === 'link_type') {
            // Keep the link_type property in sync with the source.
            $field->offsetGet($delta)->set($property, $source_offset->get($property)->getValue());
            continue;
          }

          // We directly skip if we don't have to translate this property.
          continue;
        }

        // If for a given property we don't have a translation value but we
        // do have a value on the field for that property, we need to unset it
        // using the source value of that property (which will be also empty
        // most likely). This typically happens for field properties like
        // "title" from link fields when we switch from external to internal
        // and remove the title.
        if (!isset($property_data['#translation']['#text']) && $field->offsetGet($delta) && $field->offsetGet($delta)->get($property)->getValue()) {
          $field->offsetGet($delta)->set($property, $source_offset->get($property)->getValue());
          continue;
        }

        if (!isset($property_data['#translation']['#text'])) {
          // Nothing else to do if there is no translation for this property.
          continue;
        }

        // If the offset does not exist at all, populate it with the current
        // value from the source content, so that the translated field offset
        // can be saved.
        if (!$field->offsetExists(($delta))) {
          // Note that the source language value will be immediately
          // overwritten.
          $field->offsetSet($delta, $source_offset);
        }

        // If there is translation data for the field property, save it.
        $field->offsetGet($delta)->set($property, $property_data['#translation']['#text']);

        // If the field has a format, set it.
        if (isset($property_data['#format'])) {
          $field->offsetGet($delta)->set('format', $property_data['#format']);
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

    if ($property instanceof Uri) {
      $value = $property->getValue();
      if ($value && !UrlHelper::isExternal($value)) {
        return FALSE;
      }
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
