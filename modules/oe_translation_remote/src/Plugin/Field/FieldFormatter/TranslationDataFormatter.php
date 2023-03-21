<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Render\Element;
use Drupal\language\ConfigurableLanguageInterface;
use Drupal\oe_translation\TranslationFormTrait;
use Drupal\oe_translation\TranslationSourceHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Translation data' formatter.
 *
 * This is a special formatter, meant to be used dynamically and not configured
 * with a view mode as it requires a language object to render the review
 * form elements for a given translation language.
 *
 * @FieldFormatter(
 *   id = "oe_translation_remote_translation_data",
 *   label = @Translation("Translation data"),
 *   field_types = {
 *     "oe_translation_remote_translated_data"
 *   }
 * )
 */
class TranslationDataFormatter extends FormatterBase {

  use TranslationFormTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $options = parent::defaultSettings();

    // The specific language to show the translated data for. This would never
    // be configured but always used dynamically.
    $options['language'] = NULL;
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    $language = $this->settings['language'];
    if (!$language instanceof ConfigurableLanguageInterface) {
      return $element;
    }

    // Find the relevant item.
    $data = [];
    foreach ($items as $item) {
      if ($item->langcode === $language->id()) {
        $data = Json::decode($item->data);
        break;
      }
    }

    if (!$data) {
      return $element;
    }

    $element['translation'] = [
      '#type' => 'container',
    ];

    $original_data = $items->getEntity()->getData();
    $data = $this->enhanceTranslationData($data, $original_data);
    foreach (Element::children($data) as $key) {
      $data[$key] = $this->generateParagraphFieldName($data[$key]);
      $data_flattened = TranslationSourceHelper::flatten($data[$key], $key);
      $element['translation'][$key] = $this->translationFormElement($data_flattened, [], TRUE);
    }

    return $element;
  }

  /**
   * Enhances the translation data with labels and Paragraph bundle labels.
   *
   * We look in the original data to extract the values and recurse down to all
   * the nested entities.
   *
   * @param array $data
   *   The translated data.
   * @param array $original_data
   *   The original data.
   *
   * @return array
   *   The translated data.
   */
  protected function enhanceTranslationData(array $data, array $original_data): array {
    foreach (Element::children($data) as $field_name) {
      $original_field_data = $original_data[$field_name] ?? [];
      // Set the label from the original field if set. This is going to be the
      // field name.
      if (isset($original_field_data['#label'])) {
        $data[$field_name]['#label'] = $original_field_data['#label'];
      }

      foreach (Element::children($data[$field_name]) as $child_key) {
        $child = &$data[$field_name][$child_key];

        if (!isset($child['entity'])) {
          continue;
        }

        $original_field_key_data = $original_field_data[$child_key] ?? [];
        // Set the label from the original field child if set (this is going
        // to be the Delta #0 type label) when we have multiple deltas.
        if (isset($original_field_key_data['#label'])) {
          $child['#label'] = $original_field_key_data['#label'];
        }
        foreach (['#entity_type', '#entity_bundle', '#id'] as $name) {
          if (isset($original_field_key_data['entity'][$name])) {
            $child['entity'][$name] = $original_field_key_data['entity'][$name];
          }
        }

        $child['entity'] = $this->enhanceTranslationData($child['entity'], $original_field_key_data['entity']);
      }

    }

    return $data;
  }

}
