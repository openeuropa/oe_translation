<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\oe_translation\Entity\TranslationRequestLogInterface;

/**
 * Tests the Translation Request entity.
 *
 * @group batch1
 */
class TranslationRequestEntityTest extends TranslationKernelTestBase {

  /**
   * A translation request job entity to be referenced.
   *
   * @var \Drupal\oe_translation\Entity\TranslationRequestLogInterface
   */
  protected $translationRequestLog;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'options',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('oe_translation_request');
    $this->installEntitySchema('oe_translation_request_log');

    // Create test bundle.
    $type_storage = $this->container->get('entity_type.manager')->getStorage('oe_translation_request_type');
    $type_storage->create([
      'id' => 'request',
      'label' => 'Request',
    ])->save();

    // Create nodes to reference.
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

    // Create a translation request log entity to be referenced.
    $translation_request_log_storage = $this->container->get('entity_type.manager')->getStorage('oe_translation_request_log');
    $this->translationRequestLog = $translation_request_log_storage->create([
      'message' => '@status: The translation request message.',
      'variables' => [
        '@status' => 'Draft',
      ],
    ]);
    $this->translationRequestLog->save();
  }

  /**
   * Tests Translation Request entities.
   */
  public function testTranslationRequestEntity(): void {
    // Create a translation request.
    $translation_request_storage = $this->container->get('entity_type.manager')->getStorage('oe_translation_request');
    $values = [
      'bundle' => 'request',
      'translation_provider' => 'Translation provider',
      'source_language_code' => 'en',
      'logs' => [$this->translationRequestLog->id()],
    ];
    /** @var \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request */
    $translation_request = $translation_request_storage->create($values);
    $node = $this->container->get('entity_type.manager')->getStorage('node')->loadRevision(2);
    $translation_request->setContentEntity($node);
    $translation_request->save();

    // Assert values are saved and retrieved properly from the entity.
    $entity = $translation_request->getContentEntity();
    $this->assertEquals(1, $entity->id());
    $this->assertEquals(2, $entity->getRevisionId());
    $this->assertEquals('node', $entity->getEntityTypeId());
    $this->assertEquals('en', $translation_request->getSourceLanguageCode());

    $translation_request->setSourceLanguageCode('ro');
    $this->assertEquals('ro', $translation_request->getSourceLanguageCode());

    $data_for_translation = [
      'title' => 'Page title',
      'description' => 'Page description',
    ];
    $translation_request->setData($data_for_translation);
    $this->assertEquals($data_for_translation, $translation_request->getData());
    // Create a second translation request log entity.
    $log_message = $this->container->get('entity_type.manager')->getStorage('oe_translation_request_log')->create([
      'message' => 'The second translation request message.',
      'variables' => [],
      'type' => TranslationRequestLogInterface::ERROR,
    ]);
    $log_message->save();
    $translation_request->addLogMessage($log_message);
    $logs = $translation_request->getLogMessages();
    $this->assertEquals('Draft: The translation request message.', $logs[0]->getMessage());
    $this->assertEquals('info', $logs[0]->getType());
    $this->assertEquals('The second translation request message.', $logs[1]->getMessage());
    $this->assertEquals('error', $logs[1]->getType());
  }

}
