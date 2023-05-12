<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\node\NodeInterface;

/**
 * Tests accepting translations coming from Poetry.
 */
class PoetryTranslationAcceptTest extends PoetryTranslationTestBase {

  /**
   * A translation can be updated from the job item after being accepted.
   */
  public function testUpdatingTranslationFromJobItem(): void {
    $this->createNodeWithTranslatedContent(['fr']);
    $node = $this->drupalGetNodeByTitle('My node title');
    $this->assertCount(0, $node->getTranslationLanguages(FALSE));
    $this->assertNodeRevisionCount(1, $node);

    // Accept the translation.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->getSession()->getPage()->clickLink('Review translation');
    $this->getSession()->getPage()->pressButton('Accept translation');
    $node = $this->drupalGetNodeByTitle('My node title', TRUE);
    $translation = $node->getTranslation('fr');
    $this->assertEquals('My node title - fr', $translation->label());
    $this->assertNodeRevisionCount(1, $node);

    // Once the translation is accepted, it cannot be reviewed anymore on the
    // node translation overview page.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->linkNotExists('Review translation');

    // Delete the node translation and re-save it from the job item.
    $node->removeTranslation('fr');
    $node->save();
    $node = $this->drupalGetNodeByTitle('My node title', TRUE);
    $this->assertCount(0, $node->getTranslationLanguages(FALSE));
    $this->assertNodeRevisionCount(1, $node);

    // Go the jobs management page and find the job item.
    $this->drupalGet('/admin/tmgmt/jobs');
    $this->assertSession()->pageTextContainsOnce('No jobs available.');
    $this->assertSession()->pageTextNotContains('My node title');
    $this->getSession()->getPage()->selectFieldOption('State', 'Finished');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->assertSession()->pageTextContains('My node title');
    // Manage button.
    $this->click('.manage a');
    // Job item view button.
    $this->click('.dropbutton .view a');
    $this->assertSession()->pageTextContains('Job item My node title');
    $this->getSession()->getPage()->pressButton('Re-save translation');
    $this->assertSession()->pageTextContainsOnce('The translation has been saved.');

    $node = $this->drupalGetNodeByTitle('My node title', TRUE);
    $translation = $node->getTranslation('fr');
    $this->assertEquals('My node title - fr', $translation->label());
    $this->assertNodeRevisionCount(1, $node);

    // Update the translation on the node (to mimic a local translation) and
    // re-save again.
    $translation->set('title', 'My node title - fr updated');
    $translation->save();
    $node = $this->drupalGetNodeByTitle('My node title', TRUE);
    $translation = $node->getTranslation('fr');
    $this->assertEquals('My node title - fr updated', $translation->label());
    $this->assertNodeRevisionCount(1, $node);

    // Delete the translation again and advance the node revision.
    $node->removeTranslation('fr');
    $node->save();
    $this->assertNodeRevisionCount(1, $node);
    $node = $this->drupalGetNodeByTitle('My node title', TRUE);
    $this->assertCount(0, $node->getTranslationLanguages(FALSE));
    $first_revision_id = $node->getRevisionId();

    $node->setTitle('My node title - updated');
    $node->setNewRevision(TRUE);
    $node->save();
    $this->assertNodeRevisionCount(2, $node);
    $second_revision_id = $node->getRevisionId();
    $this->assertNotEquals($first_revision_id, $second_revision_id);

    // Re-accept the translation and assert it goes onto the first revision
    // because that's where the translation initiated from.
    $job_item = $this->entityTypeManager->getStorage('tmgmt_job_item')->load(1);
    $this->drupalGet($job_item->toUrl());
    $this->assertSession()->pageTextContains('Job item My node title');
    $this->getSession()->getPage()->pressButton('Re-save translation');
    $this->assertSession()->pageTextContainsOnce('The translation has been saved.');

    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');
    $node_storage->resetCache();
    $first_revision = $node_storage->loadRevision($first_revision_id);
    $second_revision = $node_storage->loadRevision($second_revision_id);
    // Only the first revision should have the translation.
    $this->assertTrue($first_revision->hasTranslation('fr'));
    $this->assertFalse($second_revision->hasTranslation('fr'));
  }

  /**
   * Creates a node with translated content from Poetry.
   *
   * @param array $languages
   *   The translated languages.
   */
  protected function createNodeWithTranslatedContent(array $languages): void {
    $this->createNodeWithRequestedJobs([
      'title' => 'My node title',
      'field_oe_demo_translatable_body' => 'My node body',
    ], $languages);

    // Accept the translations.
    $status_notification = $this->fixtureGenerator->statusNotification($this->defaultIdentifierInfo, 'ONG',
      [
        [
          'code' => 'FR',
          'date' => '30/08/2019 23:59',
          'accepted_date' => '30/09/2019 23:59',
        ],
        [
          'code' => 'ES',
          'date' => '30/08/2019 23:59',
          'accepted_date' => '30/09/2019 23:59',
        ],
      ]);

    $this->performNotification($status_notification);

    // Send the translations for each job.
    $jobs = $this->jobStorage->loadMultiple();
    $this->notifyWithDummyTranslations($jobs);
    $this->assertJobsAreTranslated();
  }

  /**
   * Asserts the number of expected revisions on a node.
   *
   * @param int $count
   *   Expected count.
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   */
  protected function assertNodeRevisionCount(int $count, NodeInterface $node): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');
    $this->assertCount($count, $node_storage->getQuery()->accessCheck(FALSE)->condition('nid', $node->id())->allRevisions()->execute());
  }

}
