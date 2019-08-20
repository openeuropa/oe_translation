<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\tmgmt\Entity\Job;

/**
 * Tests identifiers generation.
 */
class IdentifierTest extends TranslationKernelTestBase {
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dblog',
    'oe_translation_poetry',
    'system',
    'tmgmt',
    'tmgmt_content',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('tmgmt_job');
    $this->installEntitySchema('tmgmt_job_item');

    $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();

  }

  /**
   * Tests identifier generation.
   */
  public function testIdentifier(): void {

    // Define sequence setting.
    $settings = Settings::getAll();
    $settings['poetry.identifier.sequence'] = 'SEQUENCE';
    $new_settings = new Settings($settings);

    // Define two nodes for later use.
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $node_1 = $node_storage->create([
      'type' => 'page',
      'title' => 'Test page',
    ]);
    $node_1->save();
    $node_2 = $node_storage->create([
      'type' => 'page',
      'title' => 'Test page 2',
    ]);
    $node_2->save();
    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface $node */
    $node_1 = $node_storage->load($node_1->id());
    $node_2 = $node_storage->load($node_2->id());

    /** @var \Drupal\oe_translation_poetry\Poetry $poetry */
    $poetry = $this->container->get('oe_translation_poetry.client.default');

    // Test initial identifier.
    $identifier = $poetry->getIdentifierForContent($node_1);
    $this->assertEqual($identifier->getSequence(), 'SEQUENCE');
    $this->assertEqual($identifier->getNumber(), '');
    $this->assertEqual($identifier->getCode(), 'WEB');
    $this->assertEqual($identifier->getPart(), 0);
    $this->assertEqual($identifier->getVersion(), 0);

    // Simulate a previous translation for node 1.
    $job = tmgmt_job_create('en', 'de', 0, [
      'state' => Job::STATE_FINISHED,
      'poetry_request_id' => [
        'code' => 'WEB',
        'part' => '0',
        'version' => '0',
        'product' => 'TRA',
        'number' => 11111,
        'year' => 2018,
      ],
    ]);
    $job->save();
    $jobItem = $job->addItem('content', $node_1->getEntityTypeId(), $node_1->id());
    $poetry->setGlobalIdentifierNumber('11111');

    // Test identifier for node having already a job.
    $identifier = $poetry->getIdentifierForContent($node_1);
    $this->assertEqual($identifier->getSequence(), '');
    $this->assertEqual($identifier->getNumber(), '11111');
    $this->assertEqual($identifier->getPart(), '0');
    $this->assertEqual($identifier->getVersion(), '1');
    $this->assertEqual($identifier->getYear(), '2018');

    // Test identifier for node not having a job with
    // initialized number.
    $identifier = $poetry->getIdentifierForContent($node_2);
    $this->assertEqual($identifier->getSequence(), '');
    $this->assertEqual($identifier->getNumber(), '11111');
    $this->assertEqual($identifier->getPart(), '1');
    $this->assertEqual($identifier->getVersion(), '0');
    $this->assertEqual($identifier->getYear(), date('Y'));

    // Simulate a previous translation for node 1.
    $job = tmgmt_job_create('en', 'de', 0, [
      'state' => Job::STATE_FINISHED,
      'poetry_request_id' => [
        'code' => 'WEB',
        'part' => '99',
        'version' => '0',
        'product' => 'TRA',
        'number' => 11113,
        'year' => 2018,
      ],
    ]);
    $job->save();
    $jobItem = $job->addItem('content', $node_1->getEntityTypeId(), $node_1->id());
    $poetry->setGlobalIdentifierNumber('11113');

    // Test identifier for node not having a job with
    // initialized number that is already exhausted.
    $identifier = $poetry->getIdentifierForContent($node_2);
    $this->assertEqual($identifier->getSequence(), 'SEQUENCE');
    $this->assertEqual($identifier->getNumber(), '');
    $this->assertEqual($identifier->getPart(), '0');
    $this->assertEqual($identifier->getVersion(), '0');
    $this->assertEqual($identifier->getYear(), date('Y'));
  }

}
