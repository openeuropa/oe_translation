<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\ContentFormatter;

use Drupal\Component\Render\MarkupInterface;
use Drupal\oe_translation\Entity\TranslationRequestInterface;

/**
 * Interface for exporting to a given file format following DGT specifications.
 *
 * This interface is heavily inspired on the tmgmt_file format plugins.
 *
 * @see \Drupal\tmgmt_file\Format\FormatInterface
 */
interface ContentFormatterInterface {

  /**
   * Return the file content for the job data.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $request
   *   The request.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   String with the file content.
   */
  public function export(TranslationRequestInterface $request): MarkupInterface;

  /**
   * Converts an exported file content back to the translated data.
   *
   * @param string $file
   *   An HTML string to import.
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $request
   *   The request.
   *
   * @return array
   *   Translated data array.
   */
  public function import(string $file, TranslationRequestInterface $request): array;

}
