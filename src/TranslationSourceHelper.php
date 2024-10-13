<?php

declare(strict_types=1);

namespace Drupal\oe_translation;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Render\Element;

/**
 * Helper class for dealing with the translation source values.
 */
class TranslationSourceHelper {

  /**
   * String used to delimit flattened array keys.
   */
  const ARRAY_DELIMITER = '][';

  /**
   * Converts a nested data array into a flattened structure.
   *
   * This function can be used by translators to help with the data conversion.
   *
   * Nested keys will be joined together using a colon, so for example
   * $data['key1']['key2']['key3'] will be converted into
   * $flattened_data['key1][key2][key3'].
   *
   * @param array $data
   *   The nested array structure that should be flattened.
   * @param string|null $prefix
   *   Internal use only, indicates the current key prefix when recursing into
   *   the data array.
   * @param array $label
   *   Label for the data.
   *
   * @return array
   *   The flattened data array.
   */
  public static function flatten(array $data, ?string $prefix = NULL, array $label = []): array {
    $flattened_data = [];
    if (isset($data['#label'])) {
      $label[] = $data['#label'];
    }
    // Each element is either a text (has #text property defined) or has
    // children, not both.
    if (!empty($data['#text'])) {
      $flattened_data[$prefix] = $data;
      $flattened_data[$prefix]['#parent_label'] = $label;
    }
    else {
      $prefix = isset($prefix) ? $prefix . static::ARRAY_DELIMITER : '';
      foreach (Element::children($data) as $key) {
        $flattened_data += static::flatten($data[$key], $prefix . $key, $label);
      }
    }

    return $flattened_data;
  }

  /**
   * Converts a flattened data structure into a nested array.
   *
   * This function can be used by translators to help with the data conversion.
   *
   * Nested keys will be created based on the colon, so for example
   * $flattened_data['key1][key2][key3'] will be converted into
   * $data['key1']['key2']['key3'].
   *
   * @param array $flattened_data
   *   The flattened data array.
   *
   * @return array
   *   The nested data array.
   */
  public static function unflatten(array $flattened_data): array {
    $data = [];
    foreach ($flattened_data as $key => $flattened_data_entry) {
      NestedArray::setValue($data, explode(static::ARRAY_DELIMITER, $key), $flattened_data_entry);
    }

    return $data;
  }

  /**
   * Converts keys array to string key.
   *
   * There are three conventions for data keys in use. This function accepts
   * each of it and ensures a string key.
   *
   * @param array|string $key
   *   The key can be either be an array containing the keys of a nested array
   *   hierarchy path or a string.
   * @param string $delimiter
   *   Delimiter to be use in the keys string. Default is ']['.
   *
   * @return string
   *   Keys string.
   */
  public static function ensureStringKey($key, $delimiter = TranslationSourceHelper::ARRAY_DELIMITER): string {
    if (is_array($key)) {
      $key = implode($delimiter, $key);
    }

    return $key;
  }

  /**
   * Converts string keys to array keys.
   *
   * There are three conventions for data keys in use. This function accepts
   * each of it an ensures a array of keys.
   *
   * @param array|string $key
   *   The key can be either be an array containing the keys of a nested array
   *   hierarchy path or a string with '][' or '|' as delimiter.
   *
   * @return array
   *   Array of keys.
   */
  public static function ensureArrayKey($key): array {
    if (empty($key)) {
      return [];
    }
    if (!is_array($key)) {
      if (strstr($key, '|')) {
        $key = str_replace('|', static::ARRAY_DELIMITER, $key);
      }
      $key = explode(static::ARRAY_DELIMITER, $key);
    }

    return $key;
  }

  /**
   * Array filter callback for filtering untranslatable source data elements.
   *
   * @param array $value
   *   Array of values to filter.
   *
   * @return bool
   *   Whether it's translatable.
   */
  public static function filterData(array $value): bool {
    return !(empty($value['#text']) || (isset($value['#translate']) && $value['#translate'] === FALSE));
  }

  /**
   * Flattens and filters data for being translatable.
   *
   * @return array
   *   Returns a filtered array.
   */
  public static function filterTranslatable($data): array {
    return array_filter(static::flatten($data), [
      TranslationSourceHelper::class,
      'filterData',
    ]);
  }

}
