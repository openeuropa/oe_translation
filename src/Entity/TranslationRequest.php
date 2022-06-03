<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Entity;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Url;
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
  public function getTargetLanguageCodes(): array {
    return $this->get('target_language_codes')->isEmpty() ? [] : array_column($this->get('target_language_codes')->getValue(), 'value');
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetLanguageCodes(array $target_language_codes): TranslationRequestInterface {
    return $this->set('target_language_codes', $target_language_codes);
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestStatus(): string {
    return $this->get('request_status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setRequestStatus(string $request_status): TranslationRequestInterface {
    return $this->set('request_status', $request_status);
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
    $links = [
      '#type' => 'operations',
      '#links' => [],
    ];
    $cache = new CacheableMetadata();
    $edit = $this->toUrl('local-translation');
    $edit_access = $edit->access(NULL, TRUE);
    $cache->addCacheableDependency($edit_access);
    if ($edit_access->isAllowed()) {
      $links['#links']['edit'] = [
        'title' => t('Edit started translation request'),
        'url' => $edit,
      ];
    }

    $delete = $this->toUrl('delete-form');
    $query = $delete->getOption('query');
    $query['destination'] = Url::fromRoute('<current>')->toString();
    $delete->setOption('query', $query);
    $delete_access = $delete->access(NULL, TRUE);
    $cache->addCacheableDependency($delete_access);
    if ($delete_access->isAllowed()) {
      $links['#links']['delete'] = [
        'title' => t('Delete'),
        'url' => $delete,
      ];
    }

    $cache->applyTo($links);

    return $links;
  }

  /**
   * {@inheritdoc}
   */
  public static function getCreateOperationLink(ContentEntityInterface $entity, string $target_langcode, CacheableMetadata $cache): array {
    $url = Url::fromRoute('oe_translation_local.create_local_translation_request', [
      'entity_type' => $entity->getEntityTypeId(),
      'entity' => $entity->getRevisionId(),
      'source' => $entity->getUntranslated()->language()->getId(),
      'target' => $target_langcode,
    ]);
    $create_access = $url->access(NULL, TRUE);
    $cache->addCacheableDependency($create_access);
    $title = t('New translation');
    if ($create_access->isAllowed()) {
      return [
        'title' => $title,
        'url' => $url,
      ];
    }

    return [];
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

    $fields['target_language_codes'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Target languages'))
      ->setDescription(t('The language codes this request is for.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $fields['request_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel('Request status')
      ->setSetting('allowed_values', [
        TranslationRequestInterface::STATUS_DRAFT => t('Draft'),
        TranslationRequestInterface::STATUS_REVIEW => t('In review'),
        TranslationRequestInterface::STATUS_ACCEPTED => t('Accepted'),
        TranslationRequestInterface::STATUS_SYNCHRONISED => t('Synchronized'),
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setDefaultValue('draft')
      ->setDisplayConfigurable('form', TRUE);

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
