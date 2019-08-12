<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry_html_formatter;

use Drupal\Component\Render\MarkupInterface;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobInterface;

/**
 * Poetry HTML formatter.
 *
 * This service processes the content to be sent to the Poetry translation
 * service to comply with the requirements set by the DGT. It reuses many
 * functionalities from the TMGMT File module's HTML formatter plugin.
 *
 * @see \Drupal\tmgmt_file\Plugin\tmgmt_file\Format\Html
 */
class PoetryHtmlFormatter implements PoetryContentFormatterInterface {

  /**
   * Returns base64 encoded data that is safe for use in xml ids.
   *
   * @param string $data
   *   The string to be encoded.
   *
   * @return string
   *   The encoded string.
   */
  protected function encodeIdSafeBase64(string $data): string {
    // Prefix with a b to enforce that the first character is a letter.
    return 'b' . rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

  /**
   * Returns decoded id safe base64 data.
   *
   * @param string $data
   *   The string to be decoded.
   *
   * @return string
   *   The decoded string.
   */
  protected function decodeIdSafeBase64(string $data): string {
    // Remove prefixed b.
    $data = substr($data, 1);
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
  }

  /**
   * {@inheritdoc}
   */
  public function export(JobInterface $job, array $conditions = []): MarkupInterface {
    $items = [];
    foreach ($job->getItems($conditions) as $item) {
      $data = \Drupal::service('tmgmt.data')->filterTranslatable($item->getData());
      foreach ($data as $key => $value) {
        $value['#key'] = '[' . $item->id() . '][' . $key . ']';
        $items[$item->id()][$this->encodeIdSafeBase64($item->id() . '][' . $key)] = $value;
      }
    }
    $elements = [
      '#theme' => 'poetry_html_template',
      '#tjid' => $job->id(),
      '#source_language' => $job->getRemoteSourceLanguage(),
      '#target_language' => $job->getRemoteTargetLanguage(),
      '#items' => $items,
    ];
    return \Drupal::service('renderer')->renderPlain($elements);
  }

  /**
   * {@inheritdoc}
   */
  public function import(string $imported_file, bool $is_file = TRUE): array {
    $dom = new \DOMDocument();
    $is_file ? $dom->loadHTMLFile($imported_file) : $dom->loadHTML($imported_file);
    $xml = simplexml_import_dom($dom);

    $data = [];
    foreach ($xml->xpath("//div[@class='atom']") as $atom) {
      // Assets are our strings (eq fields in nodes).
      $key = $this->decodeIdSafeBase64((string) $atom['id']);
      $data[$key]['#text'] = (string) $atom;
    }
    return \Drupal::service('tmgmt.data')->unflatten($data);
  }

  /**
   * {@inheritdoc}
   */
  public function validateImport(string $imported_file, bool $is_file = TRUE) {
    $dom = new \DOMDocument();
    if (!$dom->loadHTMLFile($imported_file)) {
      return FALSE;
    }
    $xml = simplexml_import_dom($dom);

    // Collect meta information.
    $meta_tags = $xml->xpath('//meta');
    $meta = [];
    foreach ($meta_tags as $meta_tag) {
      $meta[(string) $meta_tag['name']] = (string) $meta_tag['content'];
    }

    // Check required meta tags.
    foreach (['JobID', 'languageSource', 'languageTarget'] as $name) {
      if (!isset($meta[$name])) {
        return FALSE;
      }
    }

    // Attempt to load the job.
    if (!$job = Job::load($meta['JobID'])) {
      \Drupal::messenger()->addError(t('The imported file job id @file_id is not available.', [
        '@file_id' => $meta['JobID'],
      ]));
      return FALSE;
    }

    // Check language.
    if ($meta['languageSource'] != $job->getRemoteSourceLanguage() ||
      $meta['languageTarget'] != $job->getRemoteTargetLanguage()) {
      return FALSE;
    }

    // Validation successful.
    return $job;
  }

}
