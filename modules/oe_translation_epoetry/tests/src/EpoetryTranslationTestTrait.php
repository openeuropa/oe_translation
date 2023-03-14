<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_epoetry;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\NodeInterface;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetry;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;

/**
 * Helpers for testing ePoetry.
 */
trait EpoetryTranslationTestTrait {

  /**
   * Creates a translation request for a given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $status
   *   The request status.
   * @param array $languages
   *   The language data (status + langcode.)
   *
   * @return \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface
   *   The resulting request.
   */
  protected function createNodeTranslationRequest(NodeInterface $node, string $status = TranslationRequestEpoetryInterface::STATUS_REQUEST_REQUESTED, array $languages = []): TranslationRequestEpoetryInterface {
    if (!$languages) {
      $languages[] = [
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REQUESTED,
        'langcode' => 'fr',
      ];
    }

    /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
    $request = TranslationRequestEpoetry::create([
      'bundle' => 'epoetry',
      'source_language_code' => $node->language()->getId(),
      'target_languages' => $languages,
      'translator_provider' => 'epoetry',
    ]);

    $date = new DrupalDateTime('2035-Oct-10');
    $request->setContentEntity($node);
    $data = \Drupal::service('oe_translation.translation_source_manager')->extractData($node->getUntranslated());
    $request->setData($data);
    $request->setRequestStatus($status);
    $request->setAutoAccept(FALSE);
    $request->setAutoSync(FALSE);
    $request->setDeadline($date);
    $request->setContacts([
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ]);
    $request->setRequestId(
      [
        'code' => 'DIGIT',
        'year' => date('Y'),
        'number' => 2000,
        'part' => 0,
        'version' => 0,
        'service' => 'TRA',
      ]
    );

    // Set expected ePoetry statuses based on the request status.
    if ($status == TranslationRequestEpoetryInterface::STATUS_REQUEST_REQUESTED) {
      $request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_SENT);
    }
    if (in_array($status, [
      TranslationRequestEpoetryInterface::STATUS_REQUEST_TRANSLATED,
      TranslationRequestEpoetryInterface::STATUS_REQUEST_FINISHED,
    ])) {
      $request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_EXECUTED);
    }

    return $request;
  }

}
