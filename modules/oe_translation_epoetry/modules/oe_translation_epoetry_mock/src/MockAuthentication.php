<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry_mock;

use OpenEuropa\EPoetry\Authentication\AuthenticationInterface;

/**
 * A mock authentication object.
 */
class MockAuthentication implements AuthenticationInterface {

  /**
   * A mock ticket.
   *
   * @var string
   */
  protected $ticket;

  /**
   * Constructs a MockAuthentication.
   *
   * @param string $ticket
   *   A mock ticket.
   */
  public function __construct(string $ticket) {
    $this->ticket = $ticket;
  }

  /**
   * {@inheritdoc}
   */
  public function getTicket(): string {
    return $this->ticket;
  }

}
