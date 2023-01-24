<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Entity;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the translation request entity class.
 *
 * @ContentEntityType(
 *   id = "oe_translation_request",
 *   label = @Translation("Translation Request"),
 *   label_collection = @Translation("Translation Requests"),
 *   bundle_label = @Translation("Translation Request type"),
 *   handlers = {
 *     "list_builder" = "Drupal\oe_translation\TranslationRequestListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\oe_translation\Access\TranslationRequestAccessControlHandler",
 *     "storage" = "Drupal\oe_translation\TranslationRequestStorage",
 *     "form" = {
 *       "default" = "Drupal\oe_translation\Form\TranslationRequestForm",
 *       "add" = "Drupal\oe_translation\Form\TranslationRequestForm",
 *       "edit" = "Drupal\oe_translation\Form\TranslationRequestForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\oe_translation\Routing\TranslationRequestRouteProvider",
 *     }
 *   },
 *   base_table = "oe_translation_request",
 *   admin_permission = "administer translation requests",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "bundle",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "created" = "created",
 *     "changed" = "changed",
 *     "published" = "status",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "add-form" = "/translation-request/add/{oe_translation_request_type}",
 *     "add-page" = "/translation-request/add",
 *     "canonical" = "/translation-request/{oe_translation_request}",
 *     "collection" = "/admin/content/translation-request",
 *     "edit-form" = "/translation-request/{oe_translation_request}/edit",
 *     "delete-form" = "/translation-request/{oe_translation_request}/delete",
 *     "delete-multiple-form" = "/admin/content/translation-requests/delete",
 *     "preview" = "/translation-request/{oe_translation_request}/preview/{language}",
 *   },
 *   bundle_entity_type = "oe_translation_request_type",
 *   field_ui_base_route = "entity.oe_translation_request_type.edit_form"
 * )
 */
class TranslationRequest extends ContentEntityBase implements TranslationRequestInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   *
   * When a new translation request entity is created, set the uid entity
   * reference to the current user as the creator of the entity.
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += ['uid' => \Drupal::currentUser()->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): string {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): TranslationRequestInterface {
    return $this->set('created', $timestamp);
  }

  /**
   * {@inheritdoc}
   */
  public function getContentEntity(): ?ContentEntityInterface {
    $entities = $this->get('content_entity')->referencedEntities();
    if (!$entities) {
      return NULL;
    }

    return reset($entities);
  }

  /**
   * {@inheritdoc}
   */
  public function setContentEntity(ContentEntityInterface $entity): TranslationRequestInterface {
    return $this->set('content_entity', [
      'entity_id' => $entity->id(),
      'entity_revision_id' => $entity->getRevisionId(),
      'entity_type' => $entity->getEntityTypeId(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceLanguageCode(): string {
    return $this->get('source_language_code')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSourceLanguageCode(string $source_language_code): TranslationRequestInterface {
    return $this->set('source_language_code', $source_language_code);
  }

  /**
   * {@inheritdoc}
   */
  public function getData(): array {
    return Json::decode($this->get('data')->value);
  }

  /**
   * {@inheritdoc}
   */
  public function setData(array $data): TranslationRequestInterface {
    return $this->set('data', Json::encode($data));
  }

  /**
   * {@inheritdoc}
   */
  public function getLogMessages(): array {
    return $this->get('logs')->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function addLogMessage(TranslationRequestLogInterface $log) {
    $this->get('logs')->appendItem($log);
  }

  /**
   * {@inheritdoc}
   */
  public function getOperationsLinks(): array {
    return \Drupal::service('oe_translation.translation_request_operations_provider')->getOperationsLinks($this);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['content_entity'] = BaseFieldDefinition::create('oe_translation_entity_revision_type_item')
      ->setLabel(t('Content entity'))
      ->setDescription(t('The entity being translated.'));

    $fields['source_language_code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source language'))
      ->setDescription(t('The language code of the entity type being translated.'));

    $fields['data'] = BaseFieldDefinition::create('string_long')
      ->setLabel('Data')
      ->setDescription(t('The Json encoded data to be translated.'));

    $fields['logs'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Translation request log')
      ->setDescription(t('The Translation request log.'))
      ->setSetting('target_type', 'oe_translation_request_log')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the translation request was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the translation request was last edited.'));

    return $fields;
  }

}
