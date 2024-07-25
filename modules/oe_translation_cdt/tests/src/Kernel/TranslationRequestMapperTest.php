<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_cdt\Kernel;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Site\Settings;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\oe_translation_cdt\Mapper\TranslationRequestMapper;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;
use Drupal\Tests\oe_translation_cdt\Traits\CdtTranslationTestTrait;

/**
 * Tests the TranslationRequestMapper class.
 *
 * @group batch1
 */
class TranslationRequestMapperTest extends TranslationKernelTestBase {

  use CdtTranslationTestTrait;

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
    $test_data = self::getCommonTranslationRequestData();
    $request = $this->createTranslationRequest($test_data, ['es', 'fr'], $this->entity);
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
      $base_url = $this->container->get('router.request_context')->getCompleteBaseUrl();
      $this->assertTrue(UrlHelper::externalIsLocal($callbacks[$key]->getCallbackBaseUrl(), $base_url));
      $this->assertEquals($callback_type, $callbacks[$key]->getCallbackType());
    }
  }

  /**
   * Tests optional parameters.
   */
  public function testOptionalParameters(): void {
    $test_data = self::getCommonTranslationRequestData();
    unset($test_data['comments']);
    $request = $this->createTranslationRequest($test_data, ['es'], $this->entity);
    $dto = $this->requestMapper->convertEntityToDto($request);

    $this->assertEquals('', $dto->getComments());
  }

  /**
   * Tests required parameters.
   */
  public function testRequiredParameters(): void {
    $this->expectException(\TypeError::class);
    $test_data = self::getCommonTranslationRequestData();
    unset($test_data['department']);
    $request = $this->createTranslationRequest($test_data, ['es'], $this->entity);
    $this->requestMapper->convertEntityToDto($request);
  }

  /**
   * Tests the volume calculation.
   */
  public function testVolumeCount(): void {
    $test_data = self::getCommonTranslationRequestData();

    // Check simple text.
    $this->entity->set('field_longtext', 'test');
    $request = $this->createTranslationRequest($test_data, ['es'], $this->entity);
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
    $request = $this->createTranslationRequest($test_data, ['es'], $this->entity);
    $dto = $this->requestMapper->convertEntityToDto($request);
    $job = $dto->getSourceDocuments()[0]->getTranslationJobs()[0];
    $this->assertEquals(0.5, $job->getVolume());

    // Check short text with a lot of HTML tags.
    $this->entity->set('name', 'test' . str_repeat('<br>', 50));
    $this->entity->set('field_longtext', 'test' . str_repeat('<br>', 750));
    $request = $this->createTranslationRequest($test_data, ['es'], $this->entity);
    $dto = $this->requestMapper->convertEntityToDto($request);
    $job = $dto->getSourceDocuments()[0]->getTranslationJobs()[0];
    $this->assertEquals(0.5, $job->getVolume());

    // Check two-page text.
    $this->entity->set('name', str_repeat('x', 100));
    $this->entity->set('field_longtext', str_repeat('x', 1400));
    $request = $this->createTranslationRequest($test_data, ['es'], $this->entity);
    $dto = $this->requestMapper->convertEntityToDto($request);
    $job = $dto->getSourceDocuments()[0]->getTranslationJobs()[0];
    $this->assertEquals(1, $job->getVolume());

    // Check three-page text.
    $this->entity->set('field_longtext', str_repeat('x', 1600));
    $request = $this->createTranslationRequest($test_data, ['es'], $this->entity);
    $dto = $this->requestMapper->convertEntityToDto($request);
    $job = $dto->getSourceDocuments()[0]->getTranslationJobs()[0];
    $this->assertEquals(1.5, $job->getVolume());
  }

}
