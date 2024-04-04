<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\ContentFormatter;

use Composer\InstalledVersions;
use Drupal\Component\Datetime\Time;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation\TranslationSourceHelper;
use Drupal\oe_translation_cdt\Model\Transaction;
use Drupal\oe_translation_cdt\Model\TransactionItem;
use Drupal\oe_translation_cdt\Model\TransactionItemField;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * XML formatter.
 *
 * Responsible for formatting the translation data into an XML version
 * that meets the requirements of the CDT service.
 */
class XmlFormatter implements ContentFormatterInterface {

  /**
   * The serializer.
   */
  protected Serializer $serializer;

  /**
   * XmlFormatter constructor.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\Time $time
   *   The time service.
   */
  public function __construct(
    protected RendererInterface $renderer,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Time $time
  ) {}

  /**
   * {@inheritdoc}
   */
  public function export(TranslationRequestInterface $request): string {
    $fields = TranslationSourceHelper::filterTranslatable($request->getData());
    $transaction_item_fields = [];
    $character_count = 0;
    foreach ($fields as $key => $field) {
      $entity = $request->getContentEntity();
      $transaction_item_fields[] = (new TransactionItemField())
        ->setIndexType((string) $entity?->getEntityTypeId())
        ->setInstanceId((string) $entity?->id())
        ->setResourceName('][' . $key)
        ->setResourceType(self::isHtml($field['#text']) ? 'html' : 'text')
        ->setResourceLabel(implode(' / ', $field['#parent_label']))
        ->setResourceMaxSize($field['#max_length'] ?? NULL)
        ->setContent($field['#text']);
      $character_count += mb_strlen(strip_tags($field['#text']));
    }

    $entity_url = $request->getContentEntity()?->toUrl('canonical', ['absolute' => TRUE])->toString();
    $transaction_item = (new TransactionItem())
      ->setPreviewLink('NO PREVIEW LINK')
      ->setSourceReference((string) $entity_url)
      ->setTransactionItemFields($transaction_item_fields);

    $transaction = (new Transaction())
      ->setTransactionId((string) $request->id())
      ->setTransactionCode('Drupal Translation Request')
      ->setDrupalVersion(\Drupal::VERSION)
      ->setModuleVersion((string) InstalledVersions::getPrettyVersion('openeuropa/oe_translation'))
      ->setProducerDateTime(\DateTime::createFromFormat('U', (string) $this->time->getCurrentTime()))
      ->setTotalCharacterLength($character_count)
      ->setTransactionItems([$transaction_item]);

    return $this->serializer()->serialize(
      data: [
        '@xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
        '@xsi:noNamespaceSchemaLocation' => '//static.cdt.europa.eu/webtranslations/schemas/Drupal-Translation-V8-2.xsd',
        '#' => $transaction,
      ],
      format: XmlEncoder::FORMAT,
      context: [
        'xml_root_node_name' => 'Transaction',
        'xml_version' => '1.0',
        'xml_encoding' => 'UTF-8',
        'xml_standalone' => 'yes',
        'xml_format_output' => TRUE,
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function import(string $file, TranslationRequestInterface $request): array {
    // Flatted the entire original request data so we have the original bits
    // as well to combine with the translation values.
    $flattened_request_data = TranslationSourceHelper::flatten($request->getData());

    // Start a fresh array of translation data so that we can only include
    // things that actually have translation values. This is to avoid
    // duplicating a lot of storage data.
    $translation_data = [];

    /** @var \Drupal\oe_translation_cdt\Model\Transaction $transaction */
    $transaction = $this->serializer()->deserialize(
      data: $file,
      type: Transaction::class,
      format: XmlEncoder::FORMAT,
      context: [
        'xml_root_node_name' => 'Transaction',
      ]
    );
    $transaction_item = $transaction->getTransactionItems()[0] ?? NULL;
    $fields = $transaction_item ? $transaction_item->getTransactionItemFields() : [];

    foreach ($fields as $field) {
      $key = trim($field->getResourceName(), '[]');
      if (!isset($flattened_request_data[$key])) {
        continue;
      }
      $translation_data[$key] = $flattened_request_data[$key];
      $translation_data[$key]['#translation'] = ['#text' => $field->getContent()];
    }

    return TranslationSourceHelper::unflatten($translation_data);
  }

  /**
   * Creates and returns the serializer on-demand.
   *
   * @return \Symfony\Component\Serializer\Serializer
   *   The serializer.
   */
  protected function serializer(): Serializer {
    if (!isset($this->serializer)) {
      $this->serializer = new Serializer(
        normalizers: [
          new ArrayDenormalizer(),
          new DateTimeNormalizer(),
          new ObjectNormalizer(
            classMetadataFactory: new ClassMetadataFactory(new AttributeLoader()),
            propertyTypeExtractor: new PhpDocExtractor(),
          ),
        ],
        encoders: [
          new XmlEncoder(),
        ]
      );
    }
    return $this->serializer;
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
