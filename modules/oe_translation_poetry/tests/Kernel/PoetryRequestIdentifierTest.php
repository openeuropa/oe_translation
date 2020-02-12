<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\node\NodeInterface;
use Drupal\tmgmt\Entity\Job;

/**
 * Tests identifiers generation.
 */
class PoetryRequestIdentifierTest extends TranslationKernelTestBase {

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
   * Tests the identifier generation.
   */
  public function testIdentifier(): void {
    // Define some variables used throughout the test.
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    /** @var \Drupal\oe_translation_poetry\Poetry $poetry */
    $poetry = $this->container->get('oe_translation_poetry.client.default');

    // Define the sequence setting.
    $this->setSetting('poetry.identifier.sequence', 'SEQUENCE');

    // Create a node and the job used for translation.
    /** @var \Drupal\node\NodeInterface $node_one */
    $node_one = $node_storage->create([
      'type' => 'page',
      'title' => 'Test page',
    ]);
    $node_one->save();
    $job_node_one = $this->createJobForNode($node_one);

    // Test initial identifier.
    $identifier = $poetry->setIdentifierForContent($node_one, [$job_node_one]);
    // The identifier needs to contain a sequence as it's the first request.
    $this->assertEquals('SEQUENCE', $identifier->getSequence());
    // There should be no number as it's the first request.
    $this->assertEquals('', $identifier->getNumber());
    $this->assertEquals('WEB', $identifier->getCode());
    $this->assertEquals(0, $identifier->getPart());
    $this->assertEquals(0, $identifier->getVersion());
    // The date is generated dynamically.
    $this->assertEquals(date('Y'), $identifier->getYear());

    // Simulate having received a response with a number, set
    // it globally and update the job.
    $poetry->setGlobalIdentifierNumber('11111');
    $identifier_values = [
      'code' => $identifier->getCode(),
      'year' => $identifier->getYear(),
      'number' => 11111,
      'version' => $identifier->getVersion(),
      'part' => $identifier->getPart(),
      'product' => $identifier->getProduct(),
    ];
    $job_node_one->set('poetry_request_id', $identifier_values);
    $job_node_one->submitted();

    // Test identifier for node having a new job and assert that its values
    // are the same as the previous job with just the version incremented.
    $job_node_one_second = $this->createJobForNode($node_one);

    $identifier = $poetry->setIdentifierForContent($node_one, [$job_node_one_second]);
    $this->assertEquals('', $identifier->getSequence());
    $this->assertEquals('11111', $identifier->getNumber());
    $this->assertEquals('0', $identifier->getPart());
    $this->assertEquals('1', $identifier->getVersion());
    $this->assertEquals(date('Y'), $identifier->getYear());

    // Create a second node and the job used for translation.
    /** @var \Drupal\node\NodeInterface $node_two */
    $node_two = $node_storage->create([
      'type' => 'page',
      'title' => 'Test page',
    ]);
    $node_two->save();
    $job_node_two = $this->createJobForNode($node_two);

    // Test identifier for second node.
    $identifier = $poetry->setIdentifierForContent($node_two, [$job_node_two]);
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

    $job_node_one_third = $this->createJobForNode($node_one);

    // We should not be sending a number to Poetry, even though we have it
    // already because the jobs were lost. So the sequence should be sent.
    $identifier = $poetry->setIdentifierForContent($node_one, [$job_node_one_third]);
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

    $job_node_two_second = $this->createJobForNode($node_two);
    $identifier = $poetry->setIdentifierForContent($node_two, [$job_node_two_second]);
    // The identifier needs to again contain the sequence to request a new
    // number as 99 was reached.
    $this->assertEquals('SEQUENCE', $identifier->getSequence());
    $this->assertEquals('', $identifier->getNumber());
    $this->assertEquals('0', $identifier->getPart());
    $this->assertEquals('0', $identifier->getVersion());
    $this->assertEquals(date('Y'), $identifier->getYear());

  }

  /**
   * Creates a job and a job item related with given node.
   *
   * State is unprocessed to mock the jobs that were not yet sent in a
   * translation request.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for the job to be associated.
   *
   * @return \Drupal\tmgmt\JobInterface
   *   The created job.
   */
  protected function createJobForNode(NodeInterface $node) {

    /** @var \Drupal\tmgmt\JobInterface $job */
    $job = tmgmt_job_create('en', 'de', 0, [
      'state' => Job::STATE_UNPROCESSED,
      'translator' => 'poetry',
    ]);
    $job->save();
    $job->addItem('content', $node->getEntityTypeId(), $node->id());

    return $job;
  }

}
