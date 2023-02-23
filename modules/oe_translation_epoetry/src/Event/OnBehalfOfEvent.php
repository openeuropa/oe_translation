<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event thrown to determine the default onBehalfOf value for a request.
 */
class OnBehalfOfEvent extends Event {

  /**
   * The name of the event.
   */
  const NAME = 'oe_translation_epoetry.on_behalf_of_event';

  /**
   * The onBehalfOf value.
   *
   * @var string|null
   */
  protected $value;

  /**
   * Returns the onBehalfOf value.
   *
   * @return string|null
   *   The onBehalfOf value.
   */
  public function getValue(): ?string {
    return $this->value;
  }

  /**
   * Sets the onBehalfOf value.
   *
   * @param string $value
   *   The onBehalfOf value.
   */
  public function setValue(string $value): void {
    $this->value = $value;
  }

}
