<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\ContentFormatter;

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
   * @return string
   *   String with the file content.
   */
  public function export(TranslationRequestInterface $request): string;

  /**
   * Parses the translated file.
   *
   * @param string $file
   *   A string to import.
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $request
   *   The request.
   *
   * @return mixed[]
   *   Translated data.
   */
  public function import(string $file, TranslationRequestInterface $request): array;

}
