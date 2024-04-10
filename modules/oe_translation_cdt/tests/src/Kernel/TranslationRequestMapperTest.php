<?php

declare(strict_types=1);

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Site\Settings;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\oe_translation\Entity\TranslationRequest;
use Drupal\oe_translation_cdt\Mapper\TranslationRequestMapper;
use Drupal\oe_translation_cdt\TranslationRequestCdtInterface;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;

/**
 * Tests the TranslationRequestMapper class.
 *
 * @group batch1
 */
class TranslationRequestMapperTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation',
    'oe_translation_cdt',
    'oe_translation_remote',
    'entity_test',
  ];

  /**
   * The content entity.
   */
  private ContentEntityInterface $entity;

  /**
   * The translation request mapper.
   */
  private TranslationRequestMapper $requestMapper;

  private const COMMON_REQUEST_DATA = [
    'bundle' => 'cdt',
    'cdt_id' => '12345',
    'comments' => 'test_comments',
    'confidentiality' => 'test_confidentiality',
    'contact_usernames' => [
      'test_contact1',
      'test_contact2',
    ],
    'deliver_to' => [
      'test_deliver_to1',
      'test_deliver_to2',
    ],
    'department' => 'test_department',
    'phone_number' => 'test_phone_number',
    'priority' => 'test_priority',
    'source_language_code' => 'en',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('oe_translation_request');
    $this->installEntitySchema('entity_test');
    $this->installConfig([
      'oe_translation_remote',
      'oe_translation_cdt',
    ]);

    FieldStorageConfig::create([
      'field_name' => 'field_longtext',
      'entity_type' => 'entity_test',
      'type' => 'string_long',
    ])->save();

    FieldConfig::create([
      'label' => 'Long text',
      'field_name' => 'field_longtext',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();

    new Settings([
      'cdt.api_key' => 'test_api_key',
    ]);

    $this->entity = EntityTest::create([
      'name' => 'Test',
      'field_longtext' => 'The <strong>long</strong> text.',
    ]);
    $this->entity->save();

    $this->requestMapper = $this->container->get('oe_translation_cdt.translation_request_mapper');
    assert($this->requestMapper instanceof TranslationRequestMapper);
  }

  /**
   * Tests the mapping with all parameters.
   */
  public function testDtoMapper(): void {
    $test_data = self::COMMON_REQUEST_DATA;
    $request = $this->createTranslationRequest($test_data, $this->entity, ['es', 'fr']);
    $dto = $this->requestMapper->convertEntityToDto($request);

    $this->assertEquals($test_data['comments'], $dto->getComments());
    $this->assertEquals('Translation request #' . $request->id(), $dto->getTitle());
    $this->assertEquals($request->id(), $dto->getClientReference());
    $this->assertEquals('Translation', $dto->getService());
    $this->assertEquals($test_data['phone_number'], $dto->getPhoneNumber());
    $this->assertFalse($dto->isQuotationOnly());
    $this->assertEquals('Send', $dto->getSendOptions());
    $this->assertEquals('WS', $dto->getPurposeCode());
    $this->assertEquals($test_data['department'], $dto->getDepartmentCode());
    $this->assertEquals($test_data['contact_usernames'], $dto->getContactUserNames());
    $this->assertEquals($test_data['deliver_to'], $dto->getDeliveryContactUsernames());
    $this->assertEquals($test_data['priority'], $dto->getPriorityCode());

    $source_document = $dto->getSourceDocuments()[0];
    $this->assertEquals(['EN'], $source_document->getSourceLanguages());
    $this->assertFalse($source_document->isPrivate());
    $this->assertEquals('XM', $source_document->getOutputDocumentFormatCode());
    $this->assertEquals($test_data['confidentiality'], $source_document->getConfidentialityCode());

    $jobs = $source_document->getTranslationJobs();
    foreach (['ES', 'FR'] as $key => $job_language) {
      $this->assertEquals($job_language, $jobs[$key]->getTargetLanguage());
      $this->assertEquals('EN', $jobs[$key]->getSourceLanguage());
      $this->assertEquals(0.5, $jobs[$key]->getVolume());
    }

    $file = $source_document->getFile();
    $this->assertEquals(
      sprintf(
        'translation_job_%s_request.xml',
        $request->id()),
      $file->getFileName()
    );

    $callbacks = $dto->getCallbacks();
    foreach (['JOB_STATUS', 'REQUEST_STATUS'] as $key => $callback_type) {
      $this->assertEquals('test_api_key', $callbacks[$key]->getClientApiKey());
      $this->assertTrue(UrlHelper::isValid($callbacks[$key]->getCallbackBaseUrl()));
      $this->assertTrue(UrlHelper::isExternal($callbacks[$key]->getCallbackBaseUrl()));
      $this->assertEquals($callback_type, $callbacks[$key]->getCallbackType());
    }
  }

  /**
   * Tests optional parameters.
   */
  public function testOptionalParameters(): void {
    $test_data = self::COMMON_REQUEST_DATA;
    unset($test_data['comments']);
    $request = $this->createTranslationRequest($test_data, $this->entity, ['es']);
    $dto = $this->requestMapper->convertEntityToDto($request);

    $this->assertEquals('', $dto->getComments());
  }

  /**
   * Tests required parameters.
   */
  public function testRequiredParameters(): void {
    $this->expectException(TypeError::class);
    $test_data = self::COMMON_REQUEST_DATA;
    unset($test_data['department']);
    $request = $this->createTranslationRequest($test_data, $this->entity, ['es']);
    $this->requestMapper->convertEntityToDto($request);
  }

  /**
   * Tests the volume calculation.
   */
  public function testVolumeCount(): void {
    $test_data = self::COMMON_REQUEST_DATA;

    // Check simple text.
    $this->entity->set('field_longtext', 'test');
    $request = $this->createTranslationRequest($test_data, $this->entity, ['es']);
    $dto = $this->requestMapper->convertEntityToDto($request);
    $job = $dto->getSourceDocuments()[0]->getTranslationJobs()[0];
    $this->assertEquals(0.5, $job->getVolume());

    // Check short text with a lot of spaces.
    $this->entity->set('name', 'Test' . str_repeat(' ', 200));
    $this->entity->set('field_longtext', sprintf(
      '%stest%stest%s',
      str_repeat(' ', 750),
      str_repeat(' ', 750),
      str_repeat(' ', 750)
    ));
    $request = $this->createTranslationRequest($test_data, $this->entity, ['es']);
    $dto = $this->requestMapper->convertEntityToDto($request);
    $job = $dto->getSourceDocuments()[0]->getTranslationJobs()[0];
    $this->assertEquals(0.5, $job->getVolume());

    // Check short text with a lot of HTML tags.
    $this->entity->set('name', 'test' . str_repeat('<br>', 50));
    $this->entity->set('field_longtext', 'test' . str_repeat('<br>', 750));
    $request = $this->createTranslationRequest($test_data, $this->entity, ['es']);
    $dto = $this->requestMapper->convertEntityToDto($request);
    $job = $dto->getSourceDocuments()[0]->getTranslationJobs()[0];
    $this->assertEquals(0.5, $job->getVolume());

    // Check two-page text.
    $this->entity->set('name', str_repeat('x', 100));
    $this->entity->set('field_longtext', str_repeat('x', 1400));
    $request = $this->createTranslationRequest($test_data, $this->entity, ['es']);
    $dto = $this->requestMapper->convertEntityToDto($request);
    $job = $dto->getSourceDocuments()[0]->getTranslationJobs()[0];
    $this->assertEquals(1, $job->getVolume());

    // Check three-page text.
    $this->entity->set('field_longtext', str_repeat('x', 1600));
    $request = $this->createTranslationRequest($test_data, $this->entity, ['es']);
    $dto = $this->requestMapper->convertEntityToDto($request);
    $job = $dto->getSourceDocuments()[0]->getTranslationJobs()[0];
    $this->assertEquals(1.5, $job->getVolume());
  }

  /**
   * Creates a translation request.
   *
   * @param array $data
   *   The data to create the translation request.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to attach to the translation request.
   * @param array $languages
   *   The target languages.
   *
   * @return \Drupal\oe_translation_cdt\TranslationRequestCdtInterface
   *   The translation request.
   */
  private function createTranslationRequest(array $data, ContentEntityInterface $entity, array $languages): TranslationRequestCdtInterface {
    $request = TranslationRequest::create($data);
    assert($request instanceof TranslationRequestCdtInterface);
    foreach ($languages as $language) {
      $request->updateTargetLanguageStatus($language, TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED);
    }
    $request->setContentEntity($entity);
    $data = $this->container->get('oe_translation.translation_source_manager')->extractData($entity->getUntranslated());
    $request->setData($data);

    return $request;
  }

}
