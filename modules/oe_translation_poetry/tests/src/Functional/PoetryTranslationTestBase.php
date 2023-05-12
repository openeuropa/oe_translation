<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\node\NodeInterface;
use Drupal\Tests\oe_translation\Functional\TranslationTestBase;
use Drupal\Tests\oe_translation_poetry\Traits\PoetryTestTrait;
use Drupal\tmgmt\Entity\Job;

/**
 * Base class for functional tests of the Poetry integration.
 */
class PoetryTranslationTestBase extends TranslationTestBase {

  use PoetryTestTrait;

  /**
   * The job storage.
   *
   * @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface
   */
  protected $jobStorage;

  /**
   * The fixture generator.
   *
   * @var \Drupal\oe_translation_poetry_mock\PoetryMockFixturesGenerator
   */
  protected $fixtureGenerator;

  /**
   * Default identifier info to use across the tests.
   *
   * @var array
   */
  protected $defaultIdentifierInfo = [
    'code' => 'WEB',
    'part' => '0',
    'version' => '0',
    'product' => 'TRA',
    'number' => 3234,
    'year' => 2020,
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_poetry',
    'oe_translation_poetry_test',
    'oe_translation_poetry_mock',
    'oe_translation_poetry_html_formatter',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Configure the translator.
    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = \Drupal::service('entity_type.manager')->getStorage('tmgmt_translator')->load('poetry');
    $translator->setSetting('title_prefix', 'OE');
    $translator->save();

    // Unset some services from the container to force a rebuild.
    \Drupal::getContainer()->set('oe_translation_poetry.client.default', NULL);
    \Drupal::getContainer()->set('oe_translation_poetry.client_factory', NULL);
    \Drupal::getContainer()->set('oe_translation_poetry_mock.fixture_generator', NULL);

    $this->jobStorage = $this->entityTypeManager->getStorage('tmgmt_job');
    $this->fixtureGenerator = $this->container->get('oe_translation_poetry_mock.fixture_generator');
  }

  /**
   * Groups the jobs by their target language.
   *
   * It returns only the latest jobs for each language.
   *
   * @param \Drupal\tmgmt\JobInterface[] $jobs
   *   The jobs.
   *
   * @return \Drupal\tmgmt\JobInterface[]
   *   The jobs.
   */
  protected function indexJobsByLanguage(array $jobs): array {
    $grouped = [];
    foreach ($jobs as $job) {
      $grouped[$job->getTargetLangcode()] = $job;
    }

    return $grouped;
  }

  /**
   * Chooses the languages to translate from the overview page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param array $languages
   *   The language codes.
   */
  protected function createInitialTranslationJobs(NodeInterface $node, array $languages): void {
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->pageTextContains('Translations of ' . $node->label());

    $values = [];
    foreach (array_keys($languages) as $language) {
      $values["languages[$language]"] = 1;
    }

    $target_languages = count($languages) > 1 ? implode(', ', $languages) : array_shift($languages);
    $expected_title = new FormattableMarkup('Send request to DG Translation for @entity in @target_languages', [
      '@entity' => $node->label(),
      '@target_languages' => $target_languages,
    ]);

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->submitForm($values, 'Request a DGT translation for the selected languages');
    $this->assertSession()->pageTextContains($expected_title->__toString());
  }

  /**
   * Creates jobs that mimic a request having been made to Poetry.
   *
   * @param array $values
   *   The content values.
   * @param array $languages
   *   The job languages.
   *
   * @return \Drupal\node\NodeInterface
   *   The created node.
   */
  protected function createNodeWithRequestedJobs(array $values, array $languages = []): NodeInterface {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
    ] + $values);
    $node->save();

    // Create a job for given languages to mimic that the request has been
    // made to Poetry.
    foreach ($languages as $language) {
      $job = tmgmt_job_create('en', $language, 0);
      $job->translator = 'poetry';
      $job->addItem('content', 'node', $node->id());
      $job->set('poetry_request_id', $this->defaultIdentifierInfo);
      $job->set('state', Job::STATE_ACTIVE);
      $date = new \DateTime('05/04/2019');
      $job->set('poetry_request_date', $date->format('Y-m-d\TH:i:s'));
      $job->save();
    }

    // Ensure the jobs do not contain any info related to Poetry status.
    $this->jobStorage->resetCache();
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs = $this->jobStorage->loadMultiple();
    foreach ($jobs as $job) {
      $this->assertTrue($job->get('poetry_request_date_updated')->isEmpty());
      $this->assertTrue($job->get('poetry_state')->isEmpty());

      $job_items = $job->getItems();
      foreach ($job_items as $job_item) {
        $this->assertEquals($node->getRevisionId(), $job_item->get('item_rid')->value);
        $this->assertEquals($node->bundle(), $job_item->get('item_bundle')->value);
      }
    }

    return $node;
  }

  /**
   * Submits the translation request on the current page with default values.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $expected_message
   *   The expected status message on the screen.
   */
  protected function submitTranslationRequestForQueue(NodeInterface $node, string $expected_message = 'The request has been sent to DGT.'): void {
    // Submit the request form.
    $date = new \DateTime();
    $date->modify('+ 7 days');
    $values = [
      'details[date]' => $date->format('Y-m-d'),
      'details[contact][auteur]' => 'author name',
      'details[contact][secretaire]' => 'secretary name',
      'details[contact][contact]' => 'contact name',
      'details[contact][responsable]' => 'responsible name',
      'details[organisation][responsible]' => 'responsible organisation name',
      'details[organisation][author]' => 'responsible author name',
      'details[organisation][requester]' => 'responsible requester name',
      'details[comment]' => 'Translation comment',
    ];
    $this->submitForm($values, 'Send request');
    $this->assertSession()->pageTextContains($expected_message);
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations');
  }

}
