<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_active_revision\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation\EntityRevisionInfoInterface;
use Drupal\oe_translation_active_revision\ActiveRevisionInterface;
use Drupal\oe_translation_active_revision\Plugin\Field\FieldType\LanguageWithEntityRevisionItem;
use Drupal\oe_translation_corporate_workflow\CorporateWorkflowTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for the forms to add or update a mapping.
 */
abstract class MappingFormBase extends FormBase {

  use CorporateWorkflowTranslationTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity revision info service.
   *
   * @var \Drupal\oe_translation\EntityRevisionInfoInterface
   */
  protected $entityRevisionInfo;

  /**
   * Constructs a MappingFormBase.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\oe_translation\EntityRevisionInfoInterface $entityRevisionInfo
   *   The entity revision info service.
   */
  public function __construct(LanguageManagerInterface $languageManager, EntityTypeManagerInterface $entityTypeManager, EntityRevisionInfoInterface $entityRevisionInfo) {
    $this->languageManager = $languageManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityRevisionInfo = $entityRevisionInfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('oe_translation.entity_revision_info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\oe_translation_active_revision\ActiveRevisionInterface $active_revision */
    $active_revision = $form_state->get('active_revision');
    if (!$active_revision instanceof ActiveRevisionInterface) {
      $active_revision = $this->entityTypeManager->getStorage('oe_translation_active_revision')->create([
        'status' => 1,
      ]);
    }

    $langcode = $form_state->get('langcode');
    $entity_type = $form_state->get('entity_type');
    $entity_id = $form_state->get('entity_id');
    $entity_revision_id = $form_state->getValue('update_mapping');
    $applies_to_published_and_validated = $form_state->get('applies_to_published_and_validated');

    $scope = LanguageWithEntityRevisionItem::SCOPE_BOTH;
    if ($applies_to_published_and_validated === FALSE) {
      // This happens when we create a new mapping from scratch. And we ensure
      // that the new mapping scope applies to the published version only if
      // the validated one has a new translation.
      $scope = LanguageWithEntityRevisionItem::SCOPE_PUBLISHED;
    }
    $active_revision->setLanguageMapping($langcode, $entity_type, (int) $entity_id, (int) $entity_revision_id, $scope);
    $active_revision->save();
    $this->messenger()->addStatus($form_state->get('message'));
  }

  /**
   * Returns the available version options the user can pick for a given entity.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $entity_id
   *   The entity ID.
   * @param string $langcode
   *   The langcode.
   *
   * @return array
   *   The options.
   */
  protected function getVersionOptions(string $entity_type, string $entity_id, string $langcode): array {
    $entity_type_definition = $this->entityTypeManager->getDefinition($entity_type);
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $ids = $storage->getQuery()
      ->condition($entity_type_definition->getKey('id'), $entity_id)
      ->condition('version.minor', 0)
      ->accessCheck(TRUE)
      ->allRevisions()
      ->execute();

    if (!$ids) {
      return [];
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface[] $version_revisions */
    $version_revisions = $this->entityTypeManager->getStorage($entity_type)->loadMultipleRevisions(array_keys($ids));
    $options = [];
    foreach ($version_revisions as $revision) {
      if (!$revision->hasTranslation($langcode)) {
        // We don't want to include the version if it doesn't have a
        // translation.
        continue;
      }

      $latest_revision = $storage->loadRevision($storage->getLatestRevisionId($revision->id()));

      // For each major version we find, we need to load the latest revision
      // of that major because the major can be the published one but can also
      // be the validated one before it. So if there is one of each, we need to
      // include only the published one. This is to account for allowing the
      // user to pick also the validated major revision in case it hasn't yet
      // been published.
      $latest_version_revision = $this->entityRevisionInfo->getEntityRevision($revision, $langcode);
      if ($latest_version_revision->getRevisionId() == $latest_revision->getRevisionId()) {
        // Prevent the mapping to the latest revision.
        continue;
      }

      if (!isset($options[$latest_version_revision->getRevisionId()])) {
        // We need to differentiate between the versions that had a translation
        // synchronised on it so that we display in parentheses that a version
        // translation was actually carried over from a previous one. For this,
        // we need to check both the revision ID and the latest revision ID of
        // the version because the translation could have started in validated
        // but be synced in published.
        $version = $this->getEntityVersion($latest_version_revision);
        $translation_request = $latest_version_revision->getTranslation($langcode)->get('translation_request')->entity;
        $revision_ids = [
          $revision->getRevisionId(),
          $latest_version_revision->getRevisionId(),
        ];
        if (!$translation_request instanceof TranslationRequestInterface || !in_array($translation_request->getContentEntity()->getRevisionId(), $revision_ids)) {
          $version .= ' (carried over)';
        }
        $options[$latest_version_revision->getRevisionId()] = $version;
      }
    }

    return $options;
  }

}
