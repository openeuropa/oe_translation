<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\tmgmt\JobInterface;
use Drupal\user\UserInterface;

/**
 * Defines the translation request entity class.
 *
 * @ContentEntityType(
 *   id = "oe_translation_request",
 *   label = @Translation("Translation Request"),
 *   label_collection = @Translation("Translation Requests"),
 *   bundle_label = @Translation("Translation Request type"),
 *   handlers = {
 *     "view_builder" = "Drupal\oe_translation\TranslationRequestViewBuilder",
 *     "list_builder" = "Drupal\oe_translation\TranslationRequestListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\oe_translation\TranslationRequestAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\oe_translation\Form\TranslationRequestForm",
 *       "add" = "Drupal\oe_translation\Form\TranslationRequestForm",
 *       "edit" = "Drupal\oe_translation\Form\TranslationRequestForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
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
  public function getCreatedTime(): int {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime(int $timestamp): TranslationRequestInterface {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner(): UserInterface {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId(): int {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid): TranslationRequestInterface {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account): TranslationRequestInterface {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getContentEntity(): array {
    return $this->get('content_entity')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setContentEntity(array $content_entity): TranslationRequestInterface {
    $this->set('content_entity', $content_entity);
    return $this;
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
    $this->set('translation_provider', $translation_provider);
    return $this;
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
    $this->set('source_language_code', $source_language_code);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetLanguageCodes(): array {
    return $this->get('target_language_codes')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetLanguageCodes(array $target_language_codes): TranslationRequestInterface {
    $this->set('target_language_codes', $target_language_codes);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getJobs(): JobInterface {
    return $this->get('jobs')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setJobs(JobInterface $jobs): TranslationRequestInterface {
    $this->set('jobs', $jobs);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAutoAcceptTranslationsEnabled(): bool {
    return $this->get('auto_accept_translations')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslationSync(): array {
    return $this->get('translation_synchronisation')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTranslationSync(array $translation_synchronisation): TranslationRequestInterface {
    $this->set('translation_synchronisation', $translation_synchronisation);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasUpstreamTranslationEnabled(): bool {
    return $this->get('upstream_translation')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function getMessageForProvider(): string {
    return $this->get('message_for_provider')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setMessageForProvider(string $message_for_provider): TranslationRequestInterface {
    $this->set('message_for_provider', $message_for_provider);
    return $this;
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
    $this->set('request_status', $request_status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $field['content_entity'] = BaseFieldDefinition::create('oe_translation_entity_revision_type_item')
      ->setLabel(t('Content entity'))
      ->setDescription(t('Stores the entity type of the entity being translated.'));

    $fields['translation_provider'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Translation provider'))
      ->setDescription(t('Stores the TMGMT translator plugin ID.'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
      ]);

    $fields['source_language_code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source language code'))
      ->setDescription(t('Stores the language code of the entity type being translated.'))
      ->setSetting('max_length', 2)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
      ]);

    $field['target_language_codes'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Target language codes'))
      ->setDescription(t('Stores the requested language codes.'))
      ->setCardinality(-1);

    $fields['jobs'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Jobs'))
      ->setDescription(t('References the TMGMT Jobs of the translation request'))
      ->setSetting('target_type', 'tmgmt_job')
      ->setReadOnly(TRUE);

    $fields['auto_accept_translations'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Auto-accept translations'))
      ->setDescription(t('Choose if incoming translations should be auto-accepted.'))
      ->setDefaultValue(FALSE);

    $field['translation_synchronisation'] = BaseFieldDefinition::create('oe_translation_translation_sync')
      ->setLabel(t('Translation synchronisation'))
      ->setDescription(t('Stores the translation synchronisation settings.'));

    $fields['upstream_translation'] = BaseFieldDefinition::create('boolean')
      ->setLabel(T('Upstream translation'))
      ->setDescription(t('Choose if the translations that come in should be upstreamed to the latest revisions.'))
      ->setDefaultValue(FALSE);

    $fields['message_for_provider'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Message for provider'))
      ->setDescription(t('Stores the message sent to the provider.'));

    $field['request_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel('Request status:')
      ->setSetting('allowed_values', [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'cancelled' => 'Cancelled',
        'finalized' => 'Finalized',
      ])
      ->setDefaultValue('draft');

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setDescription(t('The user ID of the translation request author.'))
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the translation request was created.'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the translation request was last edited.'));

    return $fields;
  }

}
