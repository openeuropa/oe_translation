<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\ContentFormatter;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation\TranslationSourceHelper;
use Drupal\oe_translation_epoetry\EpoetryLanguageMapper;

/**
 * HTML formatter.
 *
 * Responsible for the formatting the translation data into an HTML version
 * that complies with the requirements of DGT using the ePoetry service.
 */
class HtmlFormatter implements ContentFormatterInterface {

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
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(RendererInterface $renderer, EntityTypeManagerInterface $entityTypeManager) {
    $this->renderer = $renderer;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function export(TranslationRequestInterface $request): MarkupInterface {
    $data = TranslationSourceHelper::filterTranslatable($request->getData());
    $items = [];
    foreach ($data as $key => $value) {
      $value['#key'] = '[' . $request->id() . '][' . $key . ']';
      $value['renderable'] = $this->prepareValueRenderable($value);
      $items[$request->id()][$this->encodeIdSafeBase64($request->id() . '][' . $key)] = $value;
    }

    $elements = [
      '#theme' => 'content_html_template',
      '#request_id' => $request->id(),
      '#source_language' => EpoetryLanguageMapper::getEpoetryLanguageCode($request->getSourceLanguageCode(), $request),
      '#items' => $items,
    ];

    return $this->renderer->renderRoot($elements);
  }

  /**
   * {@inheritdoc}
   */
  public function import(string $file, TranslationRequestInterface $request): array {
    // Flatted the entire original request data so we have the original bits
    // as well to combine with the translation values.
    $request_data = [$request->id() => $request->getData()];
    $flattened_request_data = TranslationSourceHelper::flatten($request_data);

    // Start a fresh array of translation data so that we can only include
    // things that actually have translation values. This is to avoid
    // duplicating a lot of storage data.
    $translation_data = [];

    $dom = new \DOMDocument();
    $dom->loadHTML($file);
    $xml = simplexml_import_dom($dom);

    if (!$xml) {
      return [];
    }

    foreach ($xml->xpath("//div[@class='atom']") as $atom) {
      $key = $this->decodeIdSafeBase64((string) $atom['id']);
      $dom->loadXML($atom->asXML());
      $node = $dom->getElementsByTagName('div')->item(0);

      if (!isset($flattened_request_data[$key])) {
        continue;
      }

      $translation_data[$key] = $flattened_request_data[$key];
      $translation_data[$key]['#translation'] = ['#text' => ''];
      foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
          $translation_data[$key]['#translation']['#text'] .= $child->nodeValue;
        }
        else {
          $translation_data[$key]['#translation']['#text'] .= $dom->saveXml($child);
        }
      }
    }

    return TranslationSourceHelper::unflatten($translation_data);
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

  /**
   * Prepares a field value to be rendered.
   *
   * Given the TMGMT structured field data, prepare a renderable array to
   * print in the template depending on the field type.
   *
   * @param array $value
   *   The field data.
   *
   * @return array
   *   The renderable array.
   */
  protected function prepareValueRenderable(array $value): array {
    return [
      '#markup' => $value['#text'],
    ];
  }

}
