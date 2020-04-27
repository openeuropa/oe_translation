<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\oe_translation_poetry\Plugin\tmgmt\Translator\PoetryTranslator;
use Drupal\tmgmt\Entity\Job;

/**
 * Tests requesting a translation update to Poetry.
 */
class PoetryTranslationUpdateRequestTest extends PoetryTranslationTestBase {

  /**
   * Tests requesting a translation update.
   */
  public function testTranslationUpdateRequest(): void {
    $node = $this->createNodeWithRequestedJobs([
      'title' => 'My node title',
      'field_oe_demo_translatable_body' => 'My node body',
    ], ['bg', 'cs']);
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->pageTextContains('Translations of ' . $node->label());
    // Assert we can not see operation buttons.
    $this->assertSession()->buttonNotExists('Request a DGT translation for the selected languages');
    $this->assertSession()->buttonNotExists('Request a DGT translation update for the selected languages');
    $this->assertSession()->buttonNotExists('Add extra languages to the ongoing DGT request');
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
    // Refresh page and check the buttons.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->buttonNotExists('Request a DGT translation for the selected languages');
    $this->assertSession()->buttonExists('Request a DGT translation update for the selected languages');
    $this->assertSession()->buttonExists('Add extra languages to the ongoing DGT request');

    // Check that the jobs have been correctly updated.
    $this->jobStorage->resetCache();
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs = $this->indexJobsByLanguage($this->jobStorage->loadMultiple());
    foreach ($jobs as $job) {
      $this->assertEquals(Job::STATE_ACTIVE, $job->getState());
      $this->assertEquals($job->get('poetry_state')->value, PoetryTranslator::POETRY_STATUS_ONGOING);
    }

    // At the moment, since there is an ongoing request to Poetry (at least one
    // ongoing job), we can make an update request which needs to include all
    // ongoing jobs and any extra we may want.
    // Use the attribute to check of the field is checked as it will be disabled
    // and removed from the field list.
    $this->assertTrue($this->getSession()->getPage()->findField('edit-languages-bg')->getAttribute('checked') === 'checked');
    $this->assertTrue($this->getSession()->getPage()->findField('edit-languages-cs')->getAttribute('checked') === 'checked');
    $this->assertSession()->fieldDisabled('edit-languages-bg');
    $this->assertSession()->fieldDisabled('edit-languages-cs');

    // Include another language in the translation update.
    $this->getSession()->getPage()->checkField('edit-languages-de');
    $this->drupalPostForm(NULL, [], 'Request a DGT translation update for the selected languages');
    $this->submitTranslationRequestForQueue($node);

    // Check that all jobs have been correctly updated (the old jobs have been
    // aborted and new ones created which should be active).
    $this->jobStorage->resetCache();

    $old_jobs = $this->indexJobsByLanguage($this->jobStorage->loadMultiple([$jobs['bg']->id(), $jobs['cs']->id()]));
    $this->assertEquals(Job::STATE_ABORTED, $old_jobs['bg']->getState());
    $this->assertEquals(Job::STATE_ABORTED, $old_jobs['cs']->getState());
    /** @var \Drupal\tmgmt\JobInterface[] $new_jobs */
    $new_jobs = $this->indexJobsByLanguage($this->jobStorage->loadMultiple());
    // The index method ensures that only the latest jobs are loaded.
    $this->assertCount(3, $new_jobs);
    // Individually assert that we have the right job languages and states.
    $this->assertEquals(Job::STATE_ACTIVE, $new_jobs['bg']->getState());
    $this->assertEquals(Job::STATE_ACTIVE, $new_jobs['cs']->getState());
    $this->assertEquals(Job::STATE_ACTIVE, $new_jobs['de']->getState());

    // Go back to the translation overview page and check the buttons again.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));

    // Assert we can not see operation buttons again.
    $this->assertSession()->buttonNotExists('Request a DGT translation for the selected languages');
    $this->assertSession()->buttonNotExists('Request a DGT translation update for the selected languages');
    $this->assertSession()->buttonNotExists('Add extra languages to the ongoing DGT request');
    $this->assertSession()->pageTextContains('No translation requests to DGT can be made until the ongoing ones have been accepted and/or translated.');

    // Send a status update accepting the translation for requested languages.
    // We need to increment the version because we already made one update
    // request on this content.
    $identifier = array_merge($this->defaultIdentifierInfo, ['version' => 1]);
    $status_notification = $this->fixtureGenerator->statusNotification($identifier, 'ONG',
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
      $this->assertEquals(Job::STATE_ACTIVE, $job->getState());
      $this->assertEquals(PoetryTranslator::POETRY_STATUS_ONGOING, $job->get('poetry_state')->value);
    }

    // Assert we see operation buttons again.
    $this->assertSession()->pageTextNotContains('No translation requests to DGT can be made until the ongoing ones have been accepted and/or translated.');
    $this->assertSession()->buttonNotExists('Request a DGT translation for the selected languages');
    $this->assertSession()->buttonExists('Request a DGT translation update for the selected languages');
    $this->assertSession()->buttonExists('Add extra languages to the ongoing DGT request');

    // Send the translations for each job.
    $this->notifyWithDummyTranslations($jobs);

    $this->jobStorage->resetCache();
    $this->entityTypeManager->getStorage('tmgmt_job_item')->resetCache();
    $jobs = $this->indexJobsByLanguage($this->jobStorage->loadMultiple());
    foreach ($jobs as $job) {
      $this->assertEquals(Job::STATE_ACTIVE, $job->getState());
      $this->assertEquals(PoetryTranslator::POETRY_STATUS_TRANSLATED, $job->get('poetry_state')->value);

      $items = $job->getItems();
      $item = reset($items);
      $data = $this->container->get('tmgmt.data')->filterTranslatable($item->getData());
      foreach ($data as $field => $info) {
        $this->assertNotEmpty($info['#translation']);
        $this->assertEquals($info['#text'] . ' - ' . $job->getTargetLangcode(), $info['#translation']['#text']);
      }
    }
  }

}
