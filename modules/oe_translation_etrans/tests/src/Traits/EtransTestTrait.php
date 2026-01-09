<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_etrans\Traits;

use Drupal\node\NodeInterface;
use Drupal\oe_translation_etrans\TranslationRequestEtrans;
use Drupal\oe_translation_etrans\TranslationRequestEtransInterface;

/**
 * Helpers for testing etranslations.
 */
trait EtransTestTrait {

  /**
   * Creates a translation request for a given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $remote_id
   *   The remote ID.
   * @param string $status
   *   The request status.
   * @param array $languages
   *   The language data (status + langcode.)
   *
   * @return \Drupal\oe_translation_etrans\TranslationRequestEtransInterface
   *   The request.
   */
  protected function createNodeTranslationRequest(NodeInterface $node, string $remote_id, string $status = TranslationRequestEtransInterface::STATUS_REQUEST_REQUESTED, array $languages = []): TranslationRequestEtransInterface {
    if (!$languages) {
      $languages[] = [
        'status' => TranslationRequestEtransInterface::STATUS_LANGUAGE_REQUESTED,
        'langcode' => 'fr',
      ];
    }

    $request = TranslationRequestEtrans::create([
      'bundle' => 'etrans',
      'source_language_code' => $node->language()->getId(),
      'target_languages' => $languages,
      'translator_provider' => 'etrans',
    ]);

    $request->setContentEntity($node);
    $data = \Drupal::service('oe_translation.translation_source_manager')->extractData($node->getUntranslated());
    $request->setData($data);
    $request->setRequestStatus($status);
    $request->setRemoteId($remote_id);
    $request->generateAccessToken();

    return $request;
  }

}
