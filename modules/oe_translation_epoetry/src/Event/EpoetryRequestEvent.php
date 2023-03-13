<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Phpro\SoapClient\Type\RequestInterface;

/**
 * Event thrown when sending out a request.
 *
 * Allows subscribers to make changes to the request object.
 */
class EpoetryRequestEvent extends Event {

  /**
   * The event name.
   */
  const NAME = 'oe_translation_epoetry_request_event';

  /**
   * The request object.
   *
   * @var \Phpro\SoapClient\Type\RequestInterface
   */
  protected $request;

  /**
   * The local translation request.
   *
   * @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface
   */
  protected $translationRequest;

  /**
   * Constructs a EpoetryRequestEvent.
   *
   * @param \Phpro\SoapClient\Type\RequestInterface $request
   *   The request object.
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $translation_request
   *   The translation request.
   */
  public function __construct(RequestInterface $request, TranslationRequestEpoetryInterface $translation_request) {
    $this->request = $request;
    $this->translationRequest = $translation_request;
  }

  /**
   * Returns the request object.
   *
   * @return \Phpro\SoapClient\Type\RequestInterface
   *   The request object.
   */
  public function getRequest(): RequestInterface {
    return $this->request;
  }

  /**
   * Returns the translation request.
   *
   * @return \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface
   *   The translation request.
   */
  public function getTranslationRequest(): TranslationRequestEpoetryInterface {
    return $this->translationRequest;
  }

}
