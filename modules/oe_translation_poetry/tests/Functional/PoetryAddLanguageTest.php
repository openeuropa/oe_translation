<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\node\NodeInterface;
use Drupal\oe_translation_poetry\Plugin\tmgmt\Translator\PoetryTranslator;
use Drupal\tmgmt\Entity\Job;

/**
 * Tests adding languages to requests made to Poetry.
 */
class PoetryAddLanguageTest extends PoetryTranslationTestBase {

  /**
   * Tests to add a language to a request.
   */
  public function testAddLanguageRequest(): void {
    $node = $this->createNodeWithRequestedJobs([
      'title' => 'My node title',
      'field_oe_demo_translatable_body' => 'My node body',
    ], ['bg', 'cs']);

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->pageTextContains('Translations of ' . $node->label());

    // Assert we can not see operation buttons.
    $this->assertSession()->buttonNotExists('Request DGT translation for the selected languages');
    $this->assertSession()->buttonNotExists('Add the new selected languages to DGT translation');

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

    $this->assertSession()->buttonExists('Add the new selected languages to DGT translation');
    $page = $this->getSession()->getPage();
    $page->checkField('edit-languages-de');
    $this->drupalPostForm(NULL, [], 'Add the new selected languages to DGT translation');
    $this->submitAddLanguagesRequestForQueue($node);

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));

    // Assert we can not see operation buttons again.
    $this->assertSession()->buttonNotExists('Request DGT translation for the selected languages');
    $this->assertSession()->buttonNotExists('Add the new selected languages to DGT translation');

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

    // Refresh page.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));

    // Assert button to add languages is back.
    $this->assertSession()->buttonExists('Add the new selected languages to DGT translation');

    // Send the translations for each job.
    $jobs = $this->jobStorage->loadMultiple();
    $this->notifyWithDummyTranslations($jobs);

    $this->jobStorage->resetCache();
    $this->entityTypeManager->getStorage('tmgmt_job_item')->resetCache();
    $jobs = $this->jobStorage->loadMultiple();
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

  /**
   * Submits the request to add languages on the current page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   */
  protected function submitAddLanguagesRequestForQueue(NodeInterface $node): void {
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

}
