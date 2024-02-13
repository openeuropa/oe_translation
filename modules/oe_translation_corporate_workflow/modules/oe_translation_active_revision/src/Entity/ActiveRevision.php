<?php

declare(strict_types=1);

namespace Drupal\oe_translation_active_revision\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\oe_translation_active_revision\ActiveRevisionInterface;
use Drupal\oe_translation_active_revision\LanguageRevisionMapping;
use Drupal\oe_translation_active_revision\Plugin\Field\FieldType\LanguageWithEntityRevisionItem;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the oe_translation_active_revision entity class.
 *
 * @ContentEntityType(
 *   id = "oe_translation_active_revision",
 *   label = @Translation("ActiveR evision"),
 *   label_collection = @Translation("Active Revisions"),
 *   label_singular = @Translation("active revision"),
 *   label_plural = @Translation("active revisions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count active revisions",
 *     plural = "@count active revisions",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\oe_translation_active_revision\ActiveRevisionListBuilder",
 *     "storage" = "Drupal\oe_translation_active_revision\ActiveRevisionStorage",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\oe_translation_active_revision\Form\ActiveRevisionForm",
 *       "edit" = "Drupal\oe_translation_active_revision\Form\ActiveRevisionForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\oe_translation_active_revision\Routing\ActiveRevisionHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "oe_translation_active_revision",
 *   admin_permission = "administer active revision settings",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/oe-translation-active-revision",
 *     "add-form" = "/admin/structure/active-revisions/add",
 *     "canonical" = "/admin/structure/active-revisions/{oe_translation_active_revision}",
 *     "edit-form" = "/admin/structure/active-revisions/{oe_translation_active_revision}",
 *     "delete-form" = "/admin/structure/active-revisions/{oe_translation_active_revision}/delete",
 *   },
 *   field_ui_base_route = "entity.oe_translation_active_revision.settings",
 * )
 */
class ActiveRevision extends ContentEntityBase implements ActiveRevisionInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 0,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner')
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
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the active revision was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
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
      ->setDescription(t('The time that the active revision was last edited.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function hasLanguageMapping(string $langcode): bool {
    $language_revisions = $this->get('field_language_revision')->getValue();
    foreach ($language_revisions as $values) {
      if ($values['langcode'] === $langcode) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function removeLanguageMapping(string $langcode): ActiveRevisionInterface {
    $language_revisions = $this->get('field_language_revision')->getValue();
    foreach ($language_revisions as $delta => &$values) {
      if ($values['langcode'] === $langcode) {
        unset($language_revisions[$delta]);
      }
    }

    $this->set('field_language_revision', array_values($language_revisions));
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setLanguageMapping(string $langcode, string $entity_type, int $entity_id, int $entity_revision_id, int $scope = LanguageWithEntityRevisionItem::SCOPE_BOTH): ActiveRevisionInterface {
    $language_revisions = $this->get('field_language_revision')->getValue();
    $changed = FALSE;
    foreach ($language_revisions as &$values) {
      if ($values['langcode'] === $langcode) {
        // If we found a record already for this language code, just update
        // the revision ID.
        $values['entity_revision_id'] = $entity_revision_id;
        $changed = TRUE;
        break;
      }
    }

    if (!$changed) {
      // It means we need a new entry.
      $language_revisions[] = [
        'langcode' => $langcode,
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
        'entity_revision_id' => $entity_revision_id,
        'scope' => $scope,
      ];
    }

    $this->set('field_language_revision', $language_revisions);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function updateMappingScope(string $langcode, string $entity_type, int $entity_id, int $scope): ActiveRevisionInterface {
    $language_revisions = $this->get('field_language_revision')->getValue();
    foreach ($language_revisions as &$values) {
      if ($values['langcode'] === $langcode) {
        $values['scope'] = $scope;
        $this->set('field_language_revision', $language_revisions);
        return $this;
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageMapping(string $langcode, ContentEntityInterface $entity): LanguageRevisionMapping {
    $is_validated = $entity->get('moderation_state')->value === 'validated';

    $language_revisions = $this->get('field_language_revision')->getValue();
    foreach ($language_revisions as $values) {
      if ($values['langcode'] === $langcode) {
        $revision_id = (int) $values['entity_revision_id'];
        if ($revision_id === 0) {
          // It means we are supposed to not show a translation at all, even
          // if the node does have on in the system.
          $mapping = new LanguageRevisionMapping($langcode, NULL);
          $mapping->setMappedToNull(TRUE);
          $mapping->setScope((int) $values['scope']);

          return $mapping;
        }

        if ($is_validated && (int) $values['scope'] === LanguageWithEntityRevisionItem::SCOPE_PUBLISHED) {
          // If the current entity is validated and the scope for the mapping
          // is only for published, we don't have a mapping.
          $mapping = new LanguageRevisionMapping($langcode, NULL);
          $mapping->setScope((int) $values['scope']);

          return $mapping;
        }

        $revision = \Drupal::entityTypeManager()->getStorage($values['entity_type'])->loadRevision($values['entity_revision_id']);
        if (!$revision instanceof ContentEntityInterface) {
          // In case the revision was deleted, we map to null.
          $mapping = new LanguageRevisionMapping($langcode, NULL);
          $mapping->setMappedToNull(TRUE);
          $mapping->setScope((int) $values['scope']);

          return $mapping;
        }

        $mapping = new LanguageRevisionMapping($langcode, $revision);
        $mapping->setScope((int) $values['scope']);
        return $mapping;
      }
    }

    return new LanguageRevisionMapping($langcode, NULL);
  }

}
