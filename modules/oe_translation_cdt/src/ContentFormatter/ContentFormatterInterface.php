<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\ContentFormatter;

use Drupal\oe_translation\Entity\TranslationRequestInterface;

/**
 * Interface for converting the XML translation requests.
 */
interface ContentFormatterInterface {

  /**
   * Converts the translation request data into an XML file.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $request
   *   The translation request entity.
   *
   * @return string
   *   String with the XML file content.
   */
  public function export(TranslationRequestInterface $request): string;

  /**
   * Parses the translated XML file.
   *
   * @param string $file
   *   The XML string to import.
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $request
   *   The translation request entity.
   *
   * @return array
   *   Translated data.
   */
  public function import(string $file, TranslationRequestInterface $request): array;

}
