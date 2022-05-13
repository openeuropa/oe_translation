<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Entity;

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
  public function getTranslationProvider(): string {
    return $this->get('translation_provider')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTranslationProvider(string $translation_provider): TranslationRequestInterface {
    return $this->set('translation_provider', $translation_provider);
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
  public function getJobs(): array {
    return $this->get('jobs')->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function getJobIds(): array {
    return $this->get('jobs')->isEmpty() ? [] : array_column($this->get('jobs')->getValue(), 'target_id');
  }

  /**
   * {@inheritdoc}
   */
  public function hasJob(string $job_id): bool {
    return in_array($job_id, $this->getJobIds());
  }

  /**
   * {@inheritdoc}
   */
  public function addJob(string $job_id): TranslationRequestInterface {
    $jobs = $this->getJobIds();
    $jobs[] = $job_id;
    return $this->set('jobs', array_unique($jobs));
  }

  /**
   * {@inheritdoc}
   */
  public function autoAcceptsTranslations(): bool {
    return (bool) $this->get('auto_accept_translations')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setAutoAcceptTranslations(bool $value): TranslationRequestInterface {
    return $this->set('auto_accept_translations', $value);
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslationSync(): array {
    return $this->get('translation_synchronisation')->first()->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setTranslationSync(array $translation_synchronisation): TranslationRequestInterface {
    return $this->set('translation_synchronisation', $translation_synchronisation);
  }

  /**
   * {@inheritdoc}
   */
  public function upstreamsTranslations(): bool {
    return (bool) $this->get('upstream_translations')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setUpstreamTranslation(bool $value): TranslationRequestInterface {
    return $this->set('upstream_translations', $value);
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage(): ?string {
    return $this->get('message')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMessage(string $message): TranslationRequestInterface {
    return $this->set('message', $message);
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
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['content_entity'] = BaseFieldDefinition::create('oe_translation_entity_revision_type_item')
      ->setLabel(t('Content entity'))
      ->setDescription(t('The entity being translated.'));

    $fields['translation_provider'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Translation provider'))
      ->setDescription(t('The TMGMT translator plugin ID.'));

    $fields['source_language_code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source language'))
      ->setDescription(t('The language code of the entity type being translated.'));

    $fields['target_language_codes'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Target languages'))
      ->setDescription(t('The language codes this request is for.'))
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $fields['jobs'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Jobs'))
      ->setDescription(t('The TMGMT Jobs of the translation request'))
      ->setSetting('target_type', 'tmgmt_job')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $fields['auto_accept_translations'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Auto-accept translations'))
      ->setDescription(t('Choose if incoming translations should be auto-accepted.'))
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['translation_synchronisation'] = BaseFieldDefinition::create('oe_translation_translation_sync')
      ->setLabel(t('Translation synchronisation'))
      ->setDescription(t('The translation synchronisation settings.'))
      ->setDisplayOptions('form', [
        'type' => 'oe_translation_translation_sync_widget',
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['upstream_translations'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Upstream translation'))
      ->setDescription(t('Choose if the translations that come in should be upstreamed to the latest revisions.'))
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['message'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Message'))
      ->setDescription(t('A message to be sent to the provider.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'settings' => [
          'rows' => 4,
        ],
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['request_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel('Request status')
      ->setSetting('allowed_values', [
        'draft' => t('Draft'),
        'sent' => t('Sent'),
        'cancelled' => t('Cancelled'),
        'finalized' => t('Finalized'),
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
      ])
      ->setDefaultValue('draft')
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the translation request was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the translation request was last edited.'));

    return $fields;
  }

}
