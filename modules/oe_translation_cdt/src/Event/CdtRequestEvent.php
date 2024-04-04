<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\oe_translation_cdt\TranslationRequestCdtInterface;
use OpenEuropa\CdtClient\Model\Request\Translation;

/**
 * Event thrown when sending out a request.
 *
 * Allows subscribers to make changes to the request object.
 */
class CdtRequestEvent extends Event {

  /**
   * The event name.
   */
  const NAME = 'oe_translation_cdt_request_event';

  /**
   * The request object.
   */
  protected Translation $request;

  /**
   * The local translation request entity.
   */
  protected TranslationRequestCdtInterface $translationRequest;

  /**
   * Constructs a CdtRequestEvent.
   *
   * @param \OpenEuropa\CdtClient\Model\Request\Translation $request
   *   The request object.
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request.
   */
  public function __construct(Translation $request, TranslationRequestCdtInterface $translation_request) {
    $this->request = $request;
    $this->translationRequest = $translation_request;
  }

  /**
   * Returns the request object.
   *
   * @return \OpenEuropa\CdtClient\Model\Request\Translation
   *   The request object.
   */
  public function getRequest(): Translation {
    return $this->request;
  }

  /**
   * Returns the translation request.
   *
   * @return \Drupal\oe_translation_cdt\TranslationRequestCdtInterface
   *   The translation request.
   */
  public function getTranslationRequest(): TranslationRequestCdtInterface {
    return $this->translationRequest;
  }

}
