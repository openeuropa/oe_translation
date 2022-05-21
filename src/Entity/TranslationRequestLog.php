<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Entity;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Translation request log entity.
 *
 * @ContentEntityType(
 *   id = "oe_translation_request_log",
 *   label = @Translation("Translation request log"),
 *   base_table = "oe_translation_request_log",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "uuid" = "uuid"
 *   }
 * )
 */
class TranslationRequestLog extends ContentEntityBase implements TranslationRequestLogInterface {

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): string {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): TranslationRequestLogInterface {
    return $this->set('created', $timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage() {
    $text = $this->message->value;
    if (!$this->variables->isEmpty()) {
      return new FormattableMarkup($text, $this->variables->first()->toArray());
    }
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function setMessage(string $message): TranslationRequestLogInterface {
    return $this->set('message', $message);
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return $this->get('type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setType(string $type): TranslationRequestLogInterface {
    if (!array_key_exists($type, TranslationRequestLog::getMessageTypes())) {
      throw new \Exception("Message type '$type' is not defined.");
    }
    return $this->set('type', $type);
  }

  /**
   * {@inheritdoc}
   */
  public static function getMessageTypes(): array {
    return [
      TranslationRequestLogInterface::INFO => t('info'),
      TranslationRequestLogInterface::ERROR => t('error'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel('Created time');

    $fields['message'] = BaseFieldDefinition::create('string_long')
      ->setLabel('Message')
      ->setDescription(t('The Translation request log message.'))
      ->setRequired(TRUE);

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel('Message type')
      ->setDefaultValue(static::INFO);

    $fields['variables'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Variables'));

    return $fields;
  }

}
