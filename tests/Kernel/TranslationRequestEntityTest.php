<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\tmgmt\Functional\TmgmtTestTrait;
use Drupal\tmgmt\JobInterface;

/**
 * Tests the Translation Request entity.
 */
class TranslationRequestEntityTest extends EntityKernelTestBase {

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
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('tmgmt_job');
    $this->installEntitySchema('oe_translation_request');

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
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'Node to be referenced in content entity',
    ]);
    $node->save();
    // Create a new revision.
    $node->setNewRevision(TRUE);
    $node->save();
  }

  /**
   * Tests Translation Request entities.
   */
  public function testTranslationRequestEntity(): void {
    $date = new \DateTime('2020-05-08');
    // Create a translation request.
    $translation_request_storage = $this->container->get('entity_type.manager')->getStorage('oe_translation_request');
    $values = [
      'bundle' => 'request',
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
      'message' => 'The message for the provider',
      'request_status' => 'draft',
    ];
    /** @var \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request */
    $translation_request = $translation_request_storage->create($values);
    $node = $this->container->get('entity_type.manager')->getStorage('node')->loadRevision(2);
    $translation_request->setContentEntity($node);
    $translation_request->save();

    // Assert values are saved and retrieved properly from the entity.
    /** @var \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request */
    $translation_request = $translation_request_storage->load($translation_request->id());
    $entity = $translation_request->getContentEntity();
    $this->assertEquals(1, $entity->id());
    $this->assertEquals(2, $entity->getRevisionId());
    $this->assertEquals('node', $entity->getEntityTypeId());
    $this->assertEquals('Translation provider', $translation_request->getTranslationProvider());
    $this->assertEquals('en', $translation_request->getSourceLanguageCode());
    $this->assertEquals([
      'fr',
      'es',
    ], $translation_request->getTargetLanguageCodes());
    $this->assertEquals(TRUE, $translation_request->autoAcceptsTranslations());
    $this->assertEquals(FALSE, $translation_request->upstreamsTranslations());
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
    $this->assertEquals('The message for the provider', $translation_request->getMessage());
    $this->assertEquals('draft', $translation_request->getRequestStatus());

    // Update some entity values.
    $translation_request->setTranslationProvider('New translation provider');
    $this->assertEquals('New translation provider', $translation_request->getTranslationProvider());

    $translation_request->setSourceLanguageCode('ro');
    $this->assertEquals('ro', $translation_request->getSourceLanguageCode());

    $translation_request->setTargetLanguageCodes(['de', 'el']);
    $this->assertEquals([
      'de',
      'el',
    ], $translation_request->getTargetLanguageCodes());

    // Create tmgmt jobs to be referenced.
    $job1 = $this->createJob('en', 'fr', 1, []);
    $job1->save();
    $job2 = $this->createJob('en', 'fr', 1, []);
    $job2->save();
    $translation_request->addJob($job1->id());
    $this->assertEquals(TRUE, $translation_request->hasJob($job1->id()));
    $this->assertEquals(FALSE, $translation_request->hasJob($job2->id()));
    $translation_request->addJob($job2->id());
    $this->assertEquals(TRUE, $translation_request->hasJob($job1->id()));
    $this->assertEquals(TRUE, $translation_request->hasJob($job2->id()));
    $this->assertEquals([$job1->id(), $job2->id()], $translation_request->getJobIds());
    $this->assertCount(2, $translation_request->getJobs());
    foreach ($translation_request->getJobs() as $job) {
      $this->assertInstanceOf(JobInterface::class, $job);
    }

    $translation_request->setAutoAcceptTranslations(FALSE);
    $this->assertEquals(FALSE, $translation_request->autoAcceptsTranslations());
    $translation_request->setTranslationSync([
      'type' => 'manual',
      'configuration' => [],
    ]);
    $this->assertEquals([
      'type' => 'manual',
      'configuration' => [],
    ], $translation_request->getTranslationSync());

    $translation_request->setUpstreamTranslation(TRUE);
    $this->assertEquals(TRUE, $translation_request->upstreamsTranslations());

    $message_for_provider = $this->randomString();
    $translation_request->setMessage($message_for_provider);
    $this->assertEquals($message_for_provider, $translation_request->getMessage());

    $translation_request->setRequestStatus('sent');
    $this->assertEquals('sent', $translation_request->getRequestStatus());
  }

}
