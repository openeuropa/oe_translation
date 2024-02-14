<?php

declare(strict_types=1);

namespace Drupal\oe_translation\TranslationSourceFieldProcessor;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Element;
use Drupal\pathauto\PathautoFieldItemList;
use Drupal\pathauto\PathautoState;

/**
 * Translation source field processor for the metatags field.
 */
class PathFieldProcessor extends DefaultFieldProcessor {

  /**
   * {@inheritdoc}
   */
  public function extractTranslatableData(FieldItemListInterface $field): array {
    $data = parent::extractTranslatableData($field);

    foreach (Element::children($data) as $key) {

      // If pathauto is enabled, do not attempt to translate the path, pathauto
      // will deal with it on the translation.
      if ($field instanceof PathautoFieldItemList && $field->pathauto == PathautoState::CREATE) {
        if (!empty($data[$key]['alias']['#translate'])) {
          $data[$key]['alias']['#translate'] = FALSE;
        }
      }

      if (!empty($data[$key]['langcode']['#translate'])) {
        $data[$key]['langcode']['#translate'] = FALSE;
      }
    }

    return $data;
  }

}
