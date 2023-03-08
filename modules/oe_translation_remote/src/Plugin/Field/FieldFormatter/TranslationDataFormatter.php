<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Render\Element;
use Drupal\language\ConfigurableLanguageInterface;
use Drupal\oe_translation\TranslationFormTrait;
use Drupal\oe_translation\TranslationSourceHelper;

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
      $data_flattened = TranslationSourceHelper::flatten($data[$key], $key);
      $element['translation'][$key] = $this->translationFormElement($data_flattened, [], TRUE);
    }

    return $element;
  }

}
