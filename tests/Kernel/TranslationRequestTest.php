<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\tmgmt\Functional\TmgmtTestTrait;

/**
 * Tests the Translation Request entity.
 */
class TranslationRequestTest extends EntityKernelTestBase {

  use TmgmtTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'options',
    'language',
    'tmgmt',
    'content_translation',
    'oe_translation',
    'views',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig([
      'language',
      'tmgmt',
      'oe_translation',
      'views',
      'node',
    ]);

    $this->installEntitySchema('node');
    $this->installEntitySchema('tmgmt_job');
    $this->installEntitySchema('oe_translation_request');
    $this->accessControlHandler = $this->container->get('entity_type.manager')->getAccessControlHandler('oe_translation_request');

    // Create test bundle.
    $type_storage = $this->container->get('entity_type.manager')->getStorage('oe_translation_request_type');
    $type_storage->create([
      'id' => 'request',
      'label' => 'Request',
    ])->save();

    // Create nodes to reference.
    $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $node_storage->create([
      'type' => 'page',
      'title' => 'Node to be referenced in content entity',
    ]);
    $node_storage->create([
      'type' => 'page',
      'title' => 'Additional node to be referenced',
    ]);
  }

  /**
   * Tests Translation Request entities.
   */
  public function testTranslationRequest(): void {
    $date = new \DateTime('2020-05-08');
    $message_for_provider = $this->randomString();
    // Create a translation request.
    $translation_request_storage = $this->container->get('entity_type.manager')->getStorage('oe_translation_request');
    $values = [
      'bundle' => 'request',
      'content_entity' => [
        'entity_id' => '1',
        'entity_revision_id' => '1',
        'entity_type' => 'node',
      ],
      'translation_provider' => 'Translation provider',
      'source_language_code' => 'en',
      'target_language_codes' => [
        'fr',
        'es',
      ],
      'auto_accept_translations' => TRUE,
      'upstream_translation' => FALSE,
      'translation_synchronisation' => [
        'type' => 'automatic',
        'configuration' => [
          'languages' => [
            'de',
            'es',
          ],
          'date' => $date->getTimestamp(),
        ],
      ],
      'message_for_provider' => $message_for_provider,
      'request_status' => 'draft',
    ];
    /** @var \Drupal\oe_translation\Entity\TranslationRequest $translation_request */
    $translation_request = $translation_request_storage->create($values);
    $translation_request->save();

    // Assert values are saved and retrieved properly from the entity.
    $translation_request = $translation_request_storage->load($translation_request->id());
    $this->assertEquals([
      'entity_id' => '1',
      'entity_revision_id' => '1',
      'entity_type' => 'node',
    ], $translation_request->getContentEntity());
    $this->assertEquals('Translation provider', $translation_request->getTranslationProvider());
    $this->assertEquals('en', $translation_request->getSourceLanguageCode());
    $target_language_codes = $translation_request->getTargetLanguageCodes();
    $this->assertEquals('fr', $target_language_codes[0]['value']);
    $this->assertEquals('es', $target_language_codes[1]['value']);
    $this->assertEquals(TRUE, $translation_request->hasAutoAcceptTranslations());
    $this->assertEquals(FALSE, $translation_request->hasUpstreamTranslation());
    $this->assertEquals([
      'type' => 'automatic',
      'configuration' => [
        'languages' => [
          'de',
          'es',
        ],
        'date' => $date->getTimestamp(),
      ],
    ], $translation_request->getTranslationSync());
    $this->assertEquals($message_for_provider, $translation_request->getMessageForProvider());
    $this->assertEquals('draft', $translation_request->getRequestStatus());

    // Update the entity values.
    $translation_request->setContentEntity([
      'entity_id' => '2',
      'entity_revision_id' => '2',
      'entity_type' => 'node',
    ]);
    $this->assertEquals([
      'entity_id' => '2',
      'entity_revision_id' => '2',
      'entity_type' => 'node',
    ], $translation_request->getContentEntity());

    $translation_request->setTranslationProvider('New translation provider');
    $this->assertEquals('New translation provider', $translation_request->getTranslationProvider());

    $translation_request->setSourceLanguageCode('ro');
    $this->assertEquals('ro', $translation_request->getSourceLanguageCode());

    $translation_request->setTargetLanguageCodes(['de', 'el']);
    $target_language_codes = $translation_request->getTargetLanguageCodes();
    $this->assertEquals('de', $target_language_codes[0]['value']);
    $this->assertEquals('el', $target_language_codes[1]['value']);

    // Create tmgmt jobs to be referenced.
    $job1 = $this->createJob('en', 'fr', 1, []);
    $job1->save();
    $job2 = $this->createJob('en', 'fr', 1, []);
    $job2->save();
    $translation_request->addJob($job1->id());
    $this->assertEquals(TRUE, $translation_request->hasJob($job1->id()));
    $this->assertEquals(FALSE, $translation_request->hasJob($job2->id()));
    $translation_request->addJob($job2->id());
    $this->assertEquals(TRUE, $translation_request->hasJob($job2->id()));
    $this->assertEquals([$job1->id(), $job2->id()], $translation_request->getJobs());

    $translation_request->setAutoAcceptTranslations(FALSE);
    $this->assertEquals(FALSE, $translation_request->hasAutoAcceptTranslations());
    $translation_request->setTranslationSync([
      'type' => 'manual',
      'configuration' => [],
    ]);
    $this->assertEquals([
      'type' => 'manual',
      'configuration' => [],
    ], $translation_request->getTranslationSync());

    $translation_request->setUpstreamTranslation(TRUE);
    $this->assertEquals(TRUE, $translation_request->hasUpstreamTranslation());

    $message_for_provider = $this->randomString();
    $translation_request->setMessageForProvider($message_for_provider);
    $this->assertEquals($message_for_provider, $translation_request->getMessageForProvider());

    $translation_request->setRequestStatus('sent');
    $this->assertEquals('sent', $translation_request->getRequestStatus());
  }

}
