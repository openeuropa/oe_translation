<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\ContentFormatter;

use Drupal\Component\Render\MarkupInterface;
use Drupal\oe_translation\Entity\TranslationRequestInterface;

/**
 * Interface for converting the translation requests.
 */
interface ContentFormatterInterface {

  /**
   * Converts the translation request data into a file.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $request
   *   The request.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   String with the file content.
   */
  public function export(TranslationRequestInterface $request): MarkupInterface;

  /**
   * Parses the translated file into the array.
   *
   * @param string $file
   *   A string to import.
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $request
   *   The request.
   *
   * @return array
   *   Translated data array.
   */
  public function import(string $file, TranslationRequestInterface $request): array;

}
