<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\ContentFormatter;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\tmgmt\Data;

/**
 * HTML formatter.
 *
 * Responsible for the formatting the translation data into an HTML version
 * that complies with the requirements of DGT using the ePoetry service.
 *
 * Inspired heavily from the TMGMT File translator plugin HTML formatter.
 *
 * @see \Drupal\tmgmt_file\Plugin\tmgmt_file\Format\Html
 */
class HtmlFormatter implements ContentFormatterInterface {

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
  public function export(TranslationRequestInterface $request): MarkupInterface {
    $data = $this->tmgmtData->filterTranslatable($request->getData());
    $items = [];
    foreach ($data as $key => $value) {
      $value['#key'] = '[' . $request->id() . '][' . $key . ']';
      $value['renderable'] = $this->prepareValueRenderable($value);
      $items[$request->id()][$this->encodeIdSafeBase64($request->id() . '][' . $key)] = $value;
    }

    $elements = [
      '#theme' => 'content_html_template',
      '#request_id' => $request->id(),
      // @todo handle language mapping.
      '#source_language' => $request->getSourceLanguageCode(),
      '#items' => $items,
    ];

    return $this->renderer->renderRoot($elements);
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
