<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry_html_formatter;

use Drupal\Component\Render\MarkupInterface;
use Drupal\tmgmt\JobInterface;

/**
 * Interface for exporting to a given file format following DGT specifications.
 *
 * This interface is heavily inspired on the tmgmt_file format plugins.
 *
 * @see \Drupal\tmgmt_file\Format\FormatInterface
 */
interface PoetryContentFormatterInterface {

  /**
   * Return the file content for the job data.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The translation job object to be exported.
   * @param array $conditions
   *   (optional) An array containing list of conditions.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   String with the file content.
   */
  public function export(JobInterface $job, array $conditions = []): MarkupInterface;

  /**
   * Converts an exported file content back to the translated data.
   *
   * @param string $imported_file
   *   Path to a file or an XML string to import.
   * @param bool $is_file
   *   (optional) Whether $imported_file is the path to a file or not.
   *
   * @return array
   *   Translated data array.
   */
  public function import(string $imported_file, bool $is_file = TRUE): array;

  /**
   * Validates that the given file is valid and can be imported.
   *
   * @param string $imported_file
   *   File path to the file to be imported.
   * @param bool $is_file
   *   (optional) Whether $imported_file is the path to a file or not.
   *
   * @return \Drupal\tmgmt\JobInterface|bool
   *   Returns the corresponding translation job entity if the import file is
   *   valid, FALSE otherwise.
   */
  public function validateImport(string $imported_file, bool $is_file = TRUE);

}
