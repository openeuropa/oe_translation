<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Functional;

use Drupal\Core\Url;

/**
 * Tests that translations are saved in the correct revision.
 */
class TranslationRevisionTest extends TranslationTestBase {

  /**
   * Tests that translations are stored in the revision they started from.
   */
  public function testTranslationRevisionIsKept() {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My node',
    ]);
    $node->save();

    // Create a local translation task.
    $this->drupalGet(Url::fromRoute('oe_translation.permission_translator.create_local_task', [
      'entity' => $node->id(),
      'source' => 'en',
      'target' => 'fr',
      'entity_type' => 'node',
    ]));
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'My node');

    // At this point the job item and local task have been created for the
    // translation and they should reference the revision ID of the node.
    $job_items = $this->entityTypeManager->getStorage('tmgmt_job_item')->loadByProperties(['item_rid' => $node->getRevisionId()]);
    $this->assertCount(1, $job_items);

    // Save a new revision of the node before finalizing the translation and
    // check we have two revisions in total.
    $node->set('title', 'My node updated');
    $node->setNewRevision();
    $node->save();
    $revisions = $node_storage->revisionIds($node);
    $this->assertCount(2, $revisions);

    // Finalize the translation and ensure the translation was set on the
    // first node revision, not the subsequent one.
    $values = [
      'Translation' => 'My node FR',
    ];
    // It should be the first local task item created so we use the ID 1.
    $url = Url::fromRoute('entity.tmgmt_local_task_item.canonical', ['tmgmt_local_task_item' => 1]);
    $this->drupalGet($url);
    $this->submitForm($values, t('Save'));

    // The node title is that of the original revision, not the new one created.
    $this->assertSession()->pageTextContains('The translation for My node has been saved as completed.');
    $node_storage->resetCache();
    $revision_ids = $node_storage->revisionIds($node);
    // The revisions are sorted by revision ID ASC.
    /** @var \Drupal\Core\Entity\TranslatableInterface $first_revision */
    $first_revision = $node_storage->loadRevision($revision_ids[0]);
    $this->assertTrue($first_revision->hasTranslation('fr'));
    $this->assertEquals('My node FR', $first_revision->getTranslation('fr')->label());
    /** @var \Drupal\Core\Entity\TranslatableInterface $second_revision */
    $second_revision = $node_storage->loadRevision($revision_ids[1]);
    $this->assertFalse($second_revision->hasTranslation('fr'));
  }

}
