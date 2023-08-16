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
   * The original extracted translation data array to be used on save.
   *
   * @var array
   */
  protected $originalData = [];

  /**
   * For the save action, whether to actually perform save.
   *
   * @var bool
   */
  protected $save = FALSE;

  /**
   * TranslationSourceEvent constructor.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The translated entity.
   * @param array $data
   *   The translation data.
   * @param string $langcode
   *   The langcode.
   * @param bool $save
   *   Whether the values are being saved.
   */
  public function __construct(ContentEntityInterface $entity, array $data, string $langcode, bool $save = TRUE) {
    $this->entity = $entity;
    $this->data = $data;
    $this->langcode = $langcode;
    $this->save = $save;
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

  /**
   * Gets the original data.
   *
   * @return array
   *   The data.
   */
  public function getOriginalData(): array {
    return $this->originalData;
  }

  /**
   * Sets the original data.
   *
   * @param array $original_data
   *   The original data.
   */
  public function setOriginalData(array $original_data): void {
    $this->originalData = $original_data;
  }

  /**
   * Checks whether the values are being saved.
   *
   * @return bool
   *   Whether the values are being saved.
   */
  public function isSave(): bool {
    return $this->save;
  }

}
