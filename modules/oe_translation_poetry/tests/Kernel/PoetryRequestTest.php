<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\oe_translation_poetry\NotificationEndpointResolver;
use Drupal\tmgmt\Entity\Job;

/**
 * Tests identifiers generation.
 */
class PoetryRequestTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dblog',
    'oe_translation_poetry_html_formatter',
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
    $this->installEntitySchema('tmgmt_message');
    $this->installEntitySchema('tmgmt_remote');

    $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();
  }

  /**
   * Tests the request identifier generation.
   */
  public function testIdentifier(): void {
    // Define the sequence setting.
    $this->setSetting('poetry.identifier.sequence', 'SEQUENCE');

    // Create two nodes for later use.
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    /** @var \Drupal\node\NodeInterface $node_one */
    $node_one = $node_storage->create([
      'type' => 'page',
      'title' => 'Test page',
    ]);
    $node_one->save();
    /** @var \Drupal\node\NodeInterface $node_two */
    $node_two = $node_storage->create([
      'type' => 'page',
      'title' => 'Test page 2',
    ]);
    $node_two->save();

    /** @var \Drupal\oe_translation_poetry\Poetry $poetry */
    $poetry = $this->container->get('oe_translation_poetry.client.default');

    // Test initial identifier.
    $identifier = $poetry->getIdentifierForContent($node_one);
    // The identifier needs to contain a sequence as it's the first request.
    $this->assertEquals('SEQUENCE', $identifier->getSequence());
    // There should be no number as it's the first request.
    $this->assertEquals('', $identifier->getNumber());
    $this->assertEquals('WEB', $identifier->getCode());
    $this->assertEquals(0, $identifier->getPart());
    $this->assertEquals(0, $identifier->getVersion());
    // The date is generated dynamically.
    $this->assertEquals(date('Y'), $identifier->getYear());

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
      'translator' => 'poetry',
    ]);
    $job->save();
    $job->addItem('content', $node_one->getEntityTypeId(), $node_one->id());
    // With this translation simulation we also simulate having received a
    // new number from Poetry and we set it globally.
    $poetry->setGlobalIdentifierNumber('11111');

    // Test identifier for node having already a job and assert that its values
    // are the same as the previous job with just the version incremented.
    $identifier = $poetry->getIdentifierForContent($node_one);
    $this->assertEquals('', $identifier->getSequence());
    $this->assertEquals('11111', $identifier->getNumber());
    $this->assertEquals('0', $identifier->getPart());
    $this->assertEquals('1', $identifier->getVersion());
    $this->assertEquals('2018', $identifier->getYear());

    // Test identifier for another node.
    $identifier = $poetry->getIdentifierForContent($node_two);
    $this->assertEquals('', $identifier->getSequence());
    // The number is set globally from the previous request.
    $this->assertEquals('11111', $identifier->getNumber());
    // The part is increased by one from the previous request.
    $this->assertEquals('1', $identifier->getPart());
    $this->assertEquals('0', $identifier->getVersion());
    // The date is generated dynamically.
    $this->assertEquals(date('Y'), $identifier->getYear());

    // Delete the jobs from the system to mimic a data loss and ensure a new
    // number is requested in this case to start over.
    $jobs = $this->container->get('entity_type.manager')->getStorage('tmgmt_job')->loadMultiple();
    foreach ($jobs as $job) {
      $job->delete();
    }

    // We should not be sending a number to Poetry, even though we have it
    // already because the jobs were lost. So the sequence should be sent.
    $identifier = $poetry->getIdentifierForContent($node_one);
    $this->assertEquals('SEQUENCE', $identifier->getSequence());
    $this->assertEquals('', $identifier->getNumber());
    $this->assertEquals('0', $identifier->getPart());
    $this->assertEquals('0', $identifier->getVersion());
    $this->assertEquals(date('Y'), $identifier->getYear());

    // Create another finished translation for the first node but increase the
    // part to 99 to mimic the end of the number. In this case we again need
    // to not be sending a number but the sequence.
    $job = tmgmt_job_create('en', 'de', 0, [
      'state' => Job::STATE_FINISHED,
      'poetry_request_id' => [
        'code' => 'WEB',
        'part' => '99',
        'version' => '0',
        'product' => 'TRA',
        'number' => 11111,
        'year' => 2018,
      ],
    ]);
    $job->save();
    $job->addItem('content', $node_one->getEntityTypeId(), $node_one->id());

    $identifier = $poetry->getIdentifierForContent($node_two);
    // The identifier needs to again contain the sequence to request a new
    // number as 99 was reached.
    $this->assertEquals('SEQUENCE', $identifier->getSequence());
    $this->assertEquals('', $identifier->getNumber());
    $this->assertEquals('0', $identifier->getPart());
    $this->assertEquals('0', $identifier->getVersion());
    $this->assertEquals(date('Y'), $identifier->getYear());
  }

  /**
   * Tests the Poetry request ID field type and formatter.
   */
  public function testPoetryRequestIdField(): void {
    FieldStorageConfig::create([
      'field_name' => 'poetry_request_id',
      'entity_type' => 'node',
      'type' => 'poetry_request_id',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'poetry_request_id',
      'bundle' => 'page',
    ])->save();

    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $poetry_id = [
      'code' => 'WEB',
      'year' => '2019',
      'number' => 122,
      'version' => 1,
      'part' => 1,
      'product' => 'TRA',
    ];

    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'Test page',
      'poetry_request_id' => $poetry_id,
    ]);

    $node->save();
    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($node->id());

    $this->assertEquals($poetry_id, $node->get('poetry_request_id')->first()->getValue());

    $builder = $this->container->get('entity_type.manager')->getViewBuilder('node');
    $build = $builder->viewField($node->get('poetry_request_id'));
    $output = $this->container->get('renderer')->renderRoot($build);
    $this->assertContains('WEB/2019/122/1/1/TRA', (string) $output);
  }

  /**
   * Tests the notification endpoint resolver.
   */
  public function testNotificationEndpointResolver(): void {
    $expected = Url::fromRoute('oe_translation_poetry.notifications')->setAbsolute()->toString(TRUE)->getGeneratedUrl();
    $this->assertEquals($expected, NotificationEndpointResolver::resolve());

    $settings = Settings::getAll();
    $settings['poetry.notification.endpoint_prefix'] = 'http://example.com';
    new Settings($settings);

    $this->assertEquals('http://example.com/localhost/poetry/notifications', NotificationEndpointResolver::resolve());
  }

}
