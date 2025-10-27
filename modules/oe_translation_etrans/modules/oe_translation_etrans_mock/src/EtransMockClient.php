<?php

declare(strict_types=1);

namespace Drupal\oe_translation_etrans_mock;

use Drupal\oe_translation_etrans\EtransClient;
use Drupal\oe_translation_etrans\EtransRequest;

/**
 * Overrides the etrans client.
 */
class EtransMockClient extends EtransClient {

  /**
   * {@inheritdoc}
   */
  public function sendRequest(EtransRequest $request) {
    // Override the delivery and endpoint if configured.
    $config = \Drupal::config('oe_translation_etrans_mock.settings');
    $delivery = $config->get('delivery_endpoint');
    $failure = $config->get('failure_endpoint');
    if ($delivery && $delivery !== '') {
      $request->setDeliveryEndpoint($delivery);
    }
    if ($failure && $failure !== '') {
      $request->setFailureEndpoint($failure);
    }
    return parent::sendRequest($request);
  }

}
