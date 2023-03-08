<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\TranslationSourceFieldProcessor;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Element;

/**
 * Translation source field processor for the link field.
 */
class LinkFieldProcessor extends DefaultFieldProcessor {

  /**
   * {@inheritdoc}
   */
  public function extractTranslatableData(FieldItemListInterface $field): array {
    $data = parent::extractTranslatableData($field);
    foreach (Element::children($data) as $key) {
      if (!empty($data[$key]['uri']['#translate'])) {
        $data[$key]['uri']['#translate'] = FALSE;
      }
    }

    return $data;
  }

}
