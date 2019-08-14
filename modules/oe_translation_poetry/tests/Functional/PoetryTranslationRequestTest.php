<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\oe_translation_poetry_mock\PoetryMock;
use Drupal\Tests\oe_translation\Functional\TranslationTestBase;
use Drupal\tmgmt\JobInterface;

/**
 * Tests the requests made to Poetry for translations.
 */
class PoetryTranslationRequestTest extends TranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_poetry',
    'oe_translation_poetry_mock',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Configure the translator.
    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $this->container->get('entity_type.manager')->getStorage('tmgmt_translator')->load('poetry');
    $translator->setSetting('service_wsdl', PoetryMock::getWsdlUrl());
    $translator->save();

    $this->container->set('oe_translation_poetry.client.default', NULL);
  }

  /**
   * Tests new translation requests.
   */
  public function testNewTranslationRequest(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $job_storage */
    $job_storage = $this->entityTypeManager->getStorage('tmgmt_job');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My node',
    ]);
    $node->save();
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->pageTextContains('Translations of My node');

    // Select Bulgarian and Czech languages and submit the form.
    $values = [
      'languages[bg]' => '1',
      'languages[cs]' => '1',
    ];
    $this->drupalPostForm($node->toUrl('drupal:content-translation-overview'), $values, 'Request DGT translation for the selected languages');
    $this->assertSession()->pageTextContains('Send request to DG Translation');

    // Check that two jobs have been created for the two languages.
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs['bg'] = $job_storage->load(1);
    $jobs['cs'] = $job_storage->load(2);
    $this->assertCount(2, $jobs);
    foreach ($jobs as $lang => $job) {
      // The jobs should still be unprocessed at this stage.
      $this->assertEqual($job->getState(), JobInterface::STATE_UNPROCESSED);
      $this->assertEqual($job->getTargetLangcode(), $lang);
    }

    // Submit the request form.
    $date = new \DateTime();
    $date->modify('+ 7 days');
    $values = [
      'details[date]' => $date->format('Y-m-d'),
      'details[contact][author]' => 'author name',
      'details[contact][secretary]' => 'secretary name',
      'details[contact][contact]' => 'contact name',
      'details[contact][responsible]' => 'responsible name',
      'details[organisation][responsible]' => 'responsible organisation name',
      'details[organisation][author]' => 'responsible author name',
      'details[organisation][requester]' => 'responsible requester name',
      'details[comment]' => 'Translation comment',
    ];
    $this->drupalPostForm(NULL, $values, 'Send request');
    $this->assertSession()->pageTextContains('The request has been sent to DGT.');
    $this->assertSession()->addressEquals('/en/node/1/translations');

    $job_storage->resetCache();

    // The jobs should have gotten submitted and the identification numbers
    // set.
    $expected_poetry_request_id = [
      'code' => 'WEB',
      'year' => date('Y'),
      // The number is the first number because it's the first request we are
      // making.
      'number' => '1000',
      // We always start with version and part 0 in the first request.
      'version' => '0',
      'part' => '0',
      'product' => 'TRA',
    ];

    foreach ($jobs as $lang => $job) {
      /** @var JobInterface $job */
      $job = $job_storage->load($job->id());
      $this->assertEqual($job->getState(), JobInterface::STATE_ACTIVE);
      $this->assertEqual($job->get('poetry_request_id')->first()->getValue(), $expected_poetry_request_id);
    }

    file_put_contents('/var/www/html/print.html', $this->getSession()->getPage()->getContent());
  }

}
