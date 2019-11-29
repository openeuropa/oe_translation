<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\node\NodeInterface;
use Drupal\oe_translation_poetry_mock\PoetryMock;
use Drupal\Tests\oe_translation\Functional\TranslationTestBase;
use Drupal\Tests\oe_translation_poetry\Traits\PoetryTestTrait;

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
    'year' => 2010,
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_poetry',
    'oe_translation_poetry_mock',
    'oe_translation_poetry_html_formatter',
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

    // Unset some services from the container to force a rebuild.
    $this->container->set('oe_translation_poetry.client.default', NULL);
    $this->container->set('oe_translation_poetry_mock.fixture_generator', NULL);

    $this->jobStorage = $this->entityTypeManager->getStorage('tmgmt_job');
    $this->fixtureGenerator = $this->container->get('oe_translation_poetry_mock.fixture_generator');
  }

  /**
   * Groups the jobs by their target language.
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
    $expected_title = new FormattableMarkup('Send request to DG Translation for @entity in @target_languages', ['@entity' => $node->label(), '@target_languages' => $target_languages]);

    $this->drupalPostForm($node->toUrl('drupal:content-translation-overview'), $values, 'Request DGT translation for the selected languages');
    $this->assertSession()->pageTextContains($expected_title->__toString());
  }

}
