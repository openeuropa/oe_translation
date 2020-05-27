<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
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
 *     "published" = "status",
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
    return $this->set('uid', $uid);
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account): TranslationRequestInterface {
    return $this->set('uid', $account->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getContentEntity(): array {
    return $this->get('content_entity')->first()->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setContentEntity(array $content_entity): TranslationRequestInterface {
    return $this->set('content_entity', $content_entity);
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
    return $this->get('target_language_codes')->getValue();
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
    $jobs = [];
    foreach ($this->get('jobs') as $job) {
      if ($job->target_id) {
        $jobs[] = $job->target_id;
      }
    }

    return $jobs;
  }

  /**
   * {@inheritdoc}
   */
  public function hasJob(string $job_id): bool {
    return in_array($job_id, $this->getJobs());
  }

  /**
   * {@inheritdoc}
   */
  public function addJob(string $job_id): TranslationRequestInterface {
    $jobs = $this->getJobs();
    $jobs[] = $job_id;
    return $this->set('jobs', array_unique($jobs));
  }

  /**
   * {@inheritdoc}
   */
  public function hasAutoAcceptTranslations(): bool {
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
  public function hasUpstreamTranslation(): bool {
    return (bool) $this->get('upstream_translation')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setUpstreamTranslation(bool $value): TranslationRequestInterface {
    return $this->set('upstream_translation', $value);
  }

  /**
   * {@inheritdoc}
   */
  public function getMessageForProvider(): string {
    return $this->get('message_for_provider')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMessageForProvider(string $message_for_provider): TranslationRequestInterface {
    return $this->set('message_for_provider', $message_for_provider);
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

    $fields['content_entity'] = BaseFieldDefinition::create('oe_translation_entity_revision_type_item')
      ->setLabel(t('Content entity'))
      ->setDescription(t('Stores the entity type of the entity being translated.'))
      ->setDisplayOptions('form', [
        'type' => 'oe_translation_entity_revision_type_widget',
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'oe_translation_entity_revision_type_formatter',
      ]);

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
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

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

    $fields['target_language_codes'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Target language codes'))
      ->setDescription(t('Stores the requested language codes.'))
      ->setCardinality(-1)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
      ]);

    $fields['jobs'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Jobs'))
      ->setDescription(t('References the TMGMT Jobs of the translation request'))
      ->setSetting('target_type', 'tmgmt_job')
      ->setCardinality(-1)
      ->setReadOnly(TRUE);

    $fields['auto_accept_translations'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Auto-accept translations'))
      ->setDescription(t('Choose if incoming translations should be auto-accepted.'))
      ->setStorageRequired(TRUE)
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'boolean',
      ]);

    $fields['translation_synchronisation'] = BaseFieldDefinition::create('oe_translation_translation_sync')
      ->setLabel(t('Translation synchronisation'))
      ->setDescription(t('Stores the translation synchronisation settings.'))
      ->setDisplayOptions('form', [
        'type' => 'oe_translation_translation_sync_widget',
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'oe_translation_translation_sync_formatter',
      ]);

    $fields['upstream_translation'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Upstream translation'))
      ->setDescription(t('Choose if the translations that come in should be upstreamed to the latest revisions.'))
      ->setStorageRequired(TRUE)
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'boolean',
      ]);

    $fields['message_for_provider'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Message for provider'))
      ->setDescription(t('Stores the message sent to the provider.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'settings' => [
          'rows' => 4,
        ],
      ]);

    $fields['request_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel('Request status:')
      ->setSetting('allowed_values', [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'cancelled' => 'Cancelled',
        'finalized' => 'Finalized',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'settings' => [
          'rows' => 4,
        ],
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
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the translation request was created.'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'timestamp',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the translation request was last edited.'));

    $fields += static::publishedBaseFieldDefinitions($entity_type);

    return $fields;
  }

}
