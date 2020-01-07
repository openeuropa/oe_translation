<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\oe_translation_poetry\Plugin\tmgmt\Translator\PoetryTranslator;
use Drupal\tmgmt\Entity\Job;

/**
 * Tests adding languages to requests made to Poetry.
 */
class PoetryUpdateRequestTest extends PoetryTranslationTestBase {

  /**
   * Tests requesting a translation update.
   */
  public function testUpdateRequestRequest(): void {
    $node = $this->createNodeWithRequestedJobs([
      'title' => 'My node title',
      'field_oe_demo_translatable_body' => 'My node body',
    ], ['bg', 'cs']);
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->pageTextContains('Translations of ' . $node->label());
    // Assert we can not see operation buttons.
    $this->assertSession()->buttonNotExists('Request DGT translation for the selected languages');
    $this->assertSession()->buttonNotExists('Request a translation update to all selected languages');
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
    // Refresh page.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));

    // Check that the jobs have been correctly updated.
    $this->jobStorage->resetCache();
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs = $this->indexJobsByLanguage($this->jobStorage->loadMultiple());
    foreach ($jobs as $job) {
      $this->assertEqual($job->getState(), Job::STATE_ACTIVE);
      $this->assertEqual($job->get('poetry_state')->value, PoetryTranslator::POETRY_STATUS_ONGOING);
    }

    $page = $this->getSession()->getPage();
    $page->checkField('edit-languages-de');
    $this->drupalPostForm(NULL, [], 'Request a translation update to all selected languages');
    $this->submitRequestInQueue($node);
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));

    // Check that all jobs have been correctly updated.
    $this->jobStorage->resetCache();
    $old_jobs = $this->indexJobsByLanguage($this->jobStorage->loadMultiple([$jobs['bg']->id(), $jobs['cs']->id()]));
    $this->assertEqual($old_jobs['bg']->getState(), Job::STATE_ABORTED);
    $this->assertEqual($old_jobs['cs']->getState(), Job::STATE_ABORTED);
    $new_jobs = $this->indexJobsByLanguage($this->jobStorage->loadMultiple());
    $this->assertEqual($new_jobs['bg']->getState(), Job::STATE_ACTIVE);
    $this->assertEqual($new_jobs['cs']->getState(), Job::STATE_ACTIVE);
    $this->assertEqual($new_jobs['de']->getState(), Job::STATE_ACTIVE);

    // Assert we can not see operation buttons again.
    $this->assertSession()->buttonNotExists('Request DGT translation for the selected languages');
    $this->assertSession()->pageTextContains('No translation requests can be made until the ongoing ones have been accepted.');

    // Send a status update accepting the translation for requested languages.
    $identifierInfo = array_merge($this->defaultIdentifierInfo, ['version' => 1]);
    $status_notification = $this->fixtureGenerator->statusNotification($identifierInfo, 'ONG',
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
        [
          'code' => 'DE',
          'date' => '05/10/2019 23:59',
          'accepted_date' => '05/10/2020 23:59',
        ],
      ]);
    $this->performNotification($status_notification);
    // Refresh page.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));

    // Check that the jobs have been correctly updated.
    $this->jobStorage->resetCache();
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs = $this->indexJobsByLanguage($this->jobStorage->loadMultiple());
    foreach ($jobs as $job) {
      $this->assertEqual($job->getState(), Job::STATE_ACTIVE);
      $this->assertEqual($job->get('poetry_state')->value, PoetryTranslator::POETRY_STATUS_ONGOING);
    }

    // Assert we see operation buttons again.
    $this->assertSession()->pageTextNotContains('No translation requests can be made until the ongoing ones have been accepted.');
    $this->assertSession()->buttonNotExists('Request DGT translation for the selected languages');
    $this->assertSession()->buttonExists('Request a translation update to all selected languages');

    // Send the translations for each job.
    $this->notifyWithDummyTranslations($jobs);

    $this->jobStorage->resetCache();
    $this->entityTypeManager->getStorage('tmgmt_job_item')->resetCache();
    $jobs = $this->indexJobsByLanguage($this->jobStorage->loadMultiple());
    foreach ($jobs as $job) {
      $this->assertEqual($job->getState(), Job::STATE_ACTIVE);
      $this->assertEqual($job->get('poetry_state')->value, PoetryTranslator::POETRY_STATUS_TRANSLATED);

      $items = $job->getItems();
      $item = reset($items);
      $data = $this->container->get('tmgmt.data')->filterTranslatable($item->getData());
      foreach ($data as $field => $info) {
        $this->assertNotEmpty($info['#translation']);
        $this->assertEqual($info['#translation']['#text'], $info['#text'] . ' - ' . $job->getTargetLangcode());
      }
    }
  }

}
