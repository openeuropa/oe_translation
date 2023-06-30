<?php

declare(strict_types=1);

namespace Drupal\oe_translation\Event;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event fired after a translation has been synchronised.
 */
class TranslationSynchronisationEvent extends Event {

  /**
   * The event name.
   */
  public const NAME = 'oe_translation.synchronisation_event';

  /**
   * The entity being translated.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The translation request.
   *
   * @var \Drupal\oe_translation\Entity\TranslationRequestInterface
   */
  protected $translationRequest;

  /**
   * The language code.
   *
   * @var string
   */
  protected $langcode;

  /**
   * Constructs a TranslationSynchronisationEvent.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being translated.
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request
   *   The translation request.
   * @param string $langcode
   *   The language code.
   */
  public function __construct(ContentEntityInterface $entity, TranslationRequestInterface $translation_request, string $langcode) {
    $this->entity = $entity;
    $this->translationRequest = $translation_request;
    $this->langcode = $langcode;
  }

  /**
   * Returns the entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Returns the translation request.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface
   *   The translation request.
   */
  public function getTranslationRequest(): TranslationRequestInterface {
    return $this->translationRequest;
  }

  /**
   * Returns the langcode.
   *
   * @return string
   *   The language code.
   */
  public function getLangcode(): string {
    return $this->langcode;
  }

}
