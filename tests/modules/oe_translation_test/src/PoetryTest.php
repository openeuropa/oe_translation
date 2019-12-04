<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_test;

use Drupal\oe_translation_poetry\Poetry;

/**
 * Test implementation of the Poetry service.
 *
 * Overriding the original service to pass user cookies that are needed for
 * functional tests.
 */
class PoetryTest extends Poetry {

  /**
   * Initializes the Poetry instance.
   */
  public function initialize(): void {
    parent::initialize();
    // Register our own service provider to override the SOAP client. We need
    // to pass the current cookies to that the SOAP client uses them in its
    // requests. This is critical for ensuring the tests work.
    $this->poetryClient->register(new PoetrySoapProvider($this->requestStack->getCurrentRequest()->cookies->all()));
  }

}
