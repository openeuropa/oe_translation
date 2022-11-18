<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Render\Element;
use Drupal\language\ConfigurableLanguageInterface;
use Drupal\oe_translation\TranslationFormTrait;
use Drupal\tmgmt\Data;
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
   * The TMGMT data.
   *
   * @var \Drupal\tmgmt\Data
   */
  protected $tmgmtData;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, Data $tmgmtData) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->tmgmtData = $tmgmtData;
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
      $container->get('tmgmt.data')
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

    foreach (Element::children($data) as $key) {
      $data_flattened = $this->tmgmtData->flatten($data[$key], $key);
      $element['translation'][$key] = $this->translationFormElement($data_flattened, [], TRUE);
    }

    return $element;
  }

}
