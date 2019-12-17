<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

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
    $this->assertSession()->buttonNotExists('Request a translation update');
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
    $this->assertEqual($jobs['bg']->getState(), Job::STATE_ACTIVE);
    $this->assertEqual($jobs['cs']->getState(), Job::STATE_ACTIVE);

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
  }

}
