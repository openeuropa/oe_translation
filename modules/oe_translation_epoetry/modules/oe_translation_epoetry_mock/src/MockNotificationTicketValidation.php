<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry_mock;

use OpenEuropa\EPoetry\TicketValidation\TicketValidationInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Overrides the EU Login ticket validation service.
 */
class MockNotificationTicketValidation implements TicketValidationInterface {

  /**
   * {@inheritdoc}
   */
  public function validate(RequestInterface $request): bool {
    return TRUE;
  }

}
