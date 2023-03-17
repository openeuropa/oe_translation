<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Used for dispatching when translating an entity.
 */
class TranslationSourceEvent extends Event {

  /**
   * Event name for the extraction part.
   */
  const EXTRACT = 'translation_source.extract';

  /**
   * Event name for the saving part.
   */
  const SAVE = 'translation_source.save';

  /**
   * The translated entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The already extracted data or the translated data.
   *
   * @var array
   */
  protected $data;

  /**
   * The main entity language or the translation target language.
   *
   * @var string
   */
  protected $langcode;

  /**
   * TranslationSourceEvent constructor.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The translated entity.
   * @param array $data
   *   The translation data.
   * @param string $langcode
   *   The langcode.
   */
  public function __construct(ContentEntityInterface $entity, array $data, string $langcode) {
    $this->entity = $entity;
    $this->data = $data;
    $this->langcode = $langcode;
  }

  /**
   * Returns the translated entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Returns the translation data.
   *
   * @return array
   *   The data.
   */
  public function getData(): array {
    return $this->data;
  }

  /**
   * Sets the translation data.
   *
   * @param array $data
   *   The data.
   */
  public function setData(array $data): void {
    $this->data = $data;
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
