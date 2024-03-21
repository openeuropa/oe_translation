<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\ContentFormatter;

use Drupal\Component\Datetime\Time;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Render\RendererInterface;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation\TranslationSourceHelper;

/**
 * XML formatter.
 *
 * Responsible for formatting the translation data into an XML version
 * that meets the requirements of the CDT service.
 */
class XmlFormatter implements ContentFormatterInterface {

  /**
   * PoetryHtmlFormatter constructor.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module installer.
   * @param \Drupal\Component\Datetime\Time $time
   *   The time service.
   */
  public function __construct(
    protected RendererInterface $renderer,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleExtensionList $moduleExtensionList,
    protected Time $time
  ) {}

  /**
   * {@inheritdoc}
   */
  public function export(TranslationRequestInterface $request): MarkupInterface {
    $fields = TranslationSourceHelper::filterTranslatable($request->getData());
    $items = [];
    $character_count = 0;
    foreach ($fields as $key => $field) {
      $items[] = [
        'index_type' => $request->getContentEntity()->getEntityTypeId(),
        'instance_id' => $request->getContentEntity()->id(),
        'res_name' => '][' . $key,
        'res_type' => self::isHtml($field['#text']) ? 'html' : 'text',
        'res_label' => implode(' / ', $field['#parent_label']),
        'res_max_size' => $field['#max_length'] ?? NULL,
        'content' => [
          '#markup' => $field['#text'],
        ],
      ];
      $character_count += mb_strlen(strip_tags($field['#text']));
    }

    $elements = [
      '#theme' => 'translation_request_xml_file',
      '#drupal_version' => \Drupal::VERSION,
      '#module_version' => $this->moduleExtensionList->getExtensionInfo('oe_translation')['version'],
      '#producer_date' => $this->time->getCurrentTime(),
      '#translations' => [
        [
          'source_reference' => $request->getContentEntity()->toUrl('canonical', ['absolute' => TRUE])->toString(),
          'fields' => $items,
        ],
      ],
      '#total_character_length' => $character_count,
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
   * Checks if the text contains HTML.
   *
   * @param string $text
   *   The text to check.
   *
   * @return bool
   *   TRUE if the text is html, FALSE otherwise.
   */
  public static function isHtml(string $text): bool {
    return $text !== \strip_tags($text);
  }

}
