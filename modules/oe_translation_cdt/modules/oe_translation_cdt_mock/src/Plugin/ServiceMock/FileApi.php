<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt_mock\Plugin\ServiceMock;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\oe_translation\TranslationSourceHelper;
use Drupal\oe_translation_cdt\ContentFormatter\ContentFormatterInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Intercepts file request made to CDT.
 *
 * @ServiceMock(
 *   id = "oe_translation_cdt_file_api",
 *   label = @Translation("CDT mocked file responses for testing."),
 *   weight = -1,
 * )
 */
class FileApi extends ServiceMockBase {

  /**
   * Constructs a FileApi object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\oe_translation_cdt\ContentFormatter\ContentFormatterInterface $xmlFormatter
   *   The XML formatter.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ModuleExtensionList $moduleExtensionList,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ContentFormatterInterface $xmlFormatter
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $moduleExtensionList, $entityTypeManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('extension.list.module'),
      $container->get('entity_type.manager'),
      $container->get('oe_translation_cdt.xml_formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEndpointUrl(): string {
    // The file URL is not present in the static settings.
    // It is defined by the Status API response.
    return 'https://example.com/api/files/:language/:id';
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    if (!$this->hasToken($request)) {
      return new Response(401, [], $this->getResponseFromFile('general_response_401.json'));
    }

    // The mocked file URL contains the translation request ID and the language.
    $parameters = $this->getRequestParameters($request);
    /** @var \Drupal\oe_translation_cdt\TranslationRequestCdtInterface|null $entity */
    $entity = $this->entityTypeManager->getStorage('oe_translation_request')->load($parameters['id']);
    if (!$entity) {
      return new Response(400, [], $this->getResponseFromFile('file_response_400.json'));
    }

    $data = TranslationSourceHelper::filterTranslatable($entity->getData());
    foreach ($data as &$field) {
      $field['#text'] = sprintf(
        '%s translation of %s',
        $parameters['language'],
        $field['#text']
      );
    }
    $entity->setData($data);
    $xml = $this->xmlFormatter->export($entity);

    return new Response(200, [], $xml);
  }

}
