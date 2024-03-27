<?php

declare(strict_types=1);

use Drupal\Component\Utility\UrlHelper;
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
    'oe_translation_cdt',
    'oe_translation_remote',
    'entity_test',
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
      'type' => 'string',
    ])->save();

    FieldConfig::create([
      'label' => 'Long text',
      'field_name' => 'field_longtext',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Tests the CDT bundle class setters and getters.
   */
  public function testDtoMapper(): void {
    $entity = EntityTest::create([
      'name' => 'Test',
      'field_longtext' => 'The <strong>long</strong> text.',
    ]);
    $entity->save();

    $test_data = [
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
    $request = TranslationRequest::create($test_data);
    assert($request instanceof TranslationRequestCdtInterface);
    $request->updateTargetLanguageStatus('es', TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED);
    $request->updateTargetLanguageStatus('fr', TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED);
    $request->setContentEntity($entity);
    $data = \Drupal::service('oe_translation.translation_source_manager')->extractData($entity->getUntranslated());
    $request->setData($data);

    new Settings([
      'cdt.api_key' => 'test_api_key',
    ]);
    $dto = TranslationRequestMapper::entityToDto($request);
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
      $this->assertEquals($callback_type, $callbacks[$key]->getCallbackType());
    }
  }

}
