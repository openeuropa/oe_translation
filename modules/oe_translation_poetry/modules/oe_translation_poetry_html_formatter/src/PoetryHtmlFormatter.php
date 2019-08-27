<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry_html_formatter;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\tmgmt\Data;
use Drupal\tmgmt\JobInterface;

/**
 * Poetry HTML formatter.
 *
 * Responsible for the formatting of translation job data into an HTML version
 * that complies with the requirements of DGT using the Poetry service.
 *
 * Inspired heavily from the TMGMT File translator plugin HTML formatter.
 *
 * @see \Drupal\tmgmt_file\Plugin\tmgmt_file\Format\Html
 */
class PoetryHtmlFormatter implements PoetryContentFormatterInterface {

  /**
   * The TMGMT Data manager.
   *
   * @var \Drupal\tmgmt\Data
   */
  protected $tmgmtData;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * PoetryHtmlFormatter constructor.
   *
   * @param \Drupal\tmgmt\Data $tmgmt_data
   *   The TMGMT Data.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(Data $tmgmt_data, RendererInterface $renderer, EntityTypeManagerInterface $entityTypeManager) {
    $this->tmgmtData = $tmgmt_data;
    $this->renderer = $renderer;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function export(EntityInterface $entity, JobInterface $job, array $conditions = []): MarkupInterface {
    $items = [];
    foreach ($job->getItems($conditions) as $item) {
      $data = $this->tmgmtData->filterTranslatable($item->getData());
      foreach ($data as $key => $value) {
        $value['#key'] = '[' . $item->id() . '][' . $key . ']';
        $items[$item->id()][$this->encodeIdSafeBase64($item->id() . '][' . $key)] = $value;
      }
    }
    $elements = [
      '#theme' => 'poetry_html_template',
      '#entity_id' => $entity->id(),
      '#source_language' => $job->getRemoteSourceLanguage(),
      '#target_language' => $job->getRemoteTargetLanguage(),
      '#items' => $items,
    ];
    return $this->renderer->renderPlain($elements);
  }

  /**
   * {@inheritdoc}
   */
  public function import(string $imported_file, bool $is_file = TRUE): array {
    $dom = new \DOMDocument();
    $is_file ? $dom->loadHTMLFile($imported_file) : $dom->loadHTML($imported_file);
    $xml = simplexml_import_dom($dom);

    if (!$xml) {
      return [];
    }

    $data = [];
    foreach ($xml->xpath("//div[@class='atom']") as $atom) {
      $key = $this->decodeIdSafeBase64((string) $atom['id']);
      $dom->loadXML($atom->asXML());
      $node = $dom->getElementsByTagName('div')->item(0);

      // Get all node children, even text node elements.
      $data[$key] = ['#text' => ''];
      foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
          $data[$key]['#text'] .= $child->nodeValue;
        }
        else {
          $data[$key]['#text'] .= $dom->saveXml($child);
        }
      }
    }
    return $this->tmgmtData->unflatten($data);
  }

  /**
   * {@inheritdoc}
   */
  public function validateImport(string $imported_file, bool $is_file = TRUE): bool {
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

    $job = $this->entityTypeManager->getStorage('tmgmt_job')->load($meta['JobID']);
    if (!$job instanceof JobInterface) {
      return FALSE;
    }

    // Check if the languages match.
    if ($meta['languageSource'] !== $job->getRemoteSourceLanguage() || $meta['languageTarget'] !== $job->getRemoteTargetLanguage()) {
      return FALSE;
    }

    return TRUE;
  }

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

}
