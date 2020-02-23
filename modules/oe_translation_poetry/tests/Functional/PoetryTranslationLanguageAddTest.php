<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\node\NodeInterface;
use Drupal\oe_translation_poetry\Plugin\tmgmt\Translator\PoetryTranslator;
use Drupal\tmgmt\JobInterface;

/**
 * Tests adding languages to requests made to Poetry.
 */
class PoetryTranslationLanguageAddTest extends PoetryTranslationTestBase {

  /**
   * Tests to add a language to a request.
   */
  public function testTranslationAddLanguagesRequest(): void {
    $node = $this->createNodeWithRequestedJobs([
      'title' => 'My node title',
      'field_oe_demo_translatable_body' => 'My node body',
    ], ['bg', 'cs']);

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->pageTextContains('Translations of ' . $node->label());

    // Assert we can not see operation buttons.
    $this->assertSession()->buttonNotExists('Request a DGT translation for the selected languages');
    $this->assertSession()->buttonNotExists('Add extra languages to the DGT request');
    $this->assertSession()->buttonNotExists('Request a DGT translation update for the selected languages');
    $this->assertSession()->pageTextContains('No translation requests to DGT can be made until the ongoing ones have been accepted and/or translated.');

    // Send a status update accepting the translation for requested languages.
    $status_notification = $this->fixtureGenerator->statusNotification($this->defaultIdentifierInfo, 'ONG',
      [
        [
          'code' => 'BG',
          'date' => '05/10/2019 23:59',
          'accepted_date' => '05/10/2020 23:59',
        ],
        [
          'code' => 'CS',
          'date' => '05/10/2019 23:59',
          'accepted_date' => '05/10/2020 23:59',
        ],
      ]);
    $this->performNotification($status_notification);
    $jobs = $this->loadJobsKeyedByLanguage();
    // The jobs should now be active and using the default identifier.
    $this->assertJobsPoetryRequestIdValues($jobs, $this->defaultIdentifierInfo);

    // Refresh page.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));

    $this->assertSession()->buttonExists('Add extra languages to the DGT request');
    $this->assertSession()->buttonExists('Request a DGT translation update for the selected languages');
    $this->assertSession()->pageTextNotContains('No translation requests to DGT can be made until the ongoing ones have been accepted and/or translated.');
    $page = $this->getSession()->getPage();

    // Add new languages to the request.
    $page->checkField('edit-languages-de');
    $this->drupalPostForm(NULL, [], 'Add extra languages to the DGT request');
    // The new language addition should create one new unprocessed job, using
    // the same request ID.
    $jobs = $this->loadJobsKeyedByLanguage();
    $this->assertEquals(JobInterface::STATE_UNPROCESSED, $jobs['de']->getState());

    // Submit the request for a new language which will activate the job.
    $this->submitTranslationRequestForQueue($node);

    // All the jobs should now be in the same active state and using the same
    // request ID.
    $jobs = $this->loadJobsKeyedByLanguage();
    $this->assertJobsPoetryRequestIdValues($jobs, $this->defaultIdentifierInfo);
    // The new language, though, should not yet be ongoing.
    $this->assertTrue($jobs['de']->get('poetry_state')->isEmpty());

    // Refresh the page.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));

    // The new language request has not been yet accepted so we should not see
    // buttons.
    $this->assertSession()->buttonNotExists('Request a DGT translation for the selected languages');
    $this->assertSession()->buttonNotExists('Add extra languages to the DGT request');
    $this->assertSession()->buttonNotExists('Request a DGT translation update for the selected languages');
    $this->assertSession()->pageTextContains('No translation requests to DGT can be made until the ongoing ones have been accepted and/or translated.');

    // Send a status update accepting the translation for requested
    // language using same identifier.
    $status_notification = $this->fixtureGenerator->statusNotification($this->defaultIdentifierInfo, 'ONG',
      [
        [
          'code' => 'DE',
          'date' => '05/10/2019 23:59',
          'accepted_date' => '05/10/2020 23:59',
        ],
      ]);
    $this->performNotification($status_notification);
    $jobs = $this->loadJobsKeyedByLanguage();
    $this->assertEquals(PoetryTranslator::POETRY_STATUS_ONGOING, $jobs['de']->get('poetry_state')->value);

    // Refresh page.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));

    // Assert button we see the update and add language buttons again.
    $this->assertSession()->buttonExists('Add extra languages to the DGT request');
    $this->assertSession()->buttonExists('Request a DGT translation update for the selected languages');
    $this->assertSession()->pageTextNotContains('No translation requests to DGT can be made until the ongoing ones have been accepted and/or translated.');

    // Send the translations for each job.
    $this->notifyWithDummyTranslations($jobs);
    $this->assertJobsAreTranslated();
    foreach ($this->loadJobsKeyedByLanguage() as $job) {
      $this->assertEquals(PoetryTranslator::POETRY_STATUS_TRANSLATED, $job->get('poetry_state')->value);
    }
  }

  /**
   * Submits the request to add languages on the current page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   */
  protected function submitTranslationRequestForQueue(NodeInterface $node): void {
    // Submit the request form.
    $date = new \DateTime();
    $date->modify('+ 7 days');
    $values = [
      'details[date]' => $date->format('Y-m-d'),
    ];
    $this->drupalPostForm(NULL, $values, 'Send request');
    $this->assertSession()->pageTextContains('The request has been sent to DGT.');
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations');
  }

  /**
   * Loads all the jobs and key them by their target language.
   *
   * It assumes there should only be one job per language in the system.
   *
   * @return \Drupal\tmgmt\JobInterface[]
   *   The jobs.
   */
  protected function loadJobsKeyedByLanguage(): array {
    $this->jobStorage->resetCache();
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs = $this->jobStorage->loadMultiple();
    $keyed = [];
    foreach ($jobs as $job) {
      $keyed[$job->getTargetLangcode()] = $job;
    }

    return $keyed;
  }

}
