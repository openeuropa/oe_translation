<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry;

use OpenEuropa\EPoetry\Authentication\AuthenticationInterface;

/**
 * A mock authentication object.
 */
class MockAuthentication implements AuthenticationInterface {

  /**
   * The ticket.
   *
   * @var string
   */
  protected $ticket;

  /**
   * Constructs a MockAuthentication.
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
