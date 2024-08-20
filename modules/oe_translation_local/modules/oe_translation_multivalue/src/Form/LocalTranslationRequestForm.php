<?php

declare(strict_types=1);

namespace Drupal\oe_translation_multivalue\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_translation\TranslationSourceManagerInterface;
use Drupal\oe_translation_local\Form\LocalTranslationRequestForm as LocalTranslationRequestFormOriginal;
use Drupal\oe_translation_local\TranslationRequestLocal;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Form handler for the translation request entity add/edit forms.
 */
class LocalTranslationRequestForm extends LocalTranslationRequestFormOriginal {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, EntityTypeManagerInterface $entity_type_manager, TranslationSourceManagerInterface $translation_source_manager, AccountInterface $current_user, EventDispatcherInterface $event_dispatcher, EntityFieldManagerInterface $entityFieldManager) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time, $entity_type_manager, $translation_source_manager, $current_user, $event_dispatcher);
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager'),
      $container->get('oe_translation.translation_source_manager'),
      $container->get('current_user'),
      $container->get('event_dispatcher'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getExistingTranslationData(TranslationRequestLocal $translation_request, string $langcode): array {
    $existing_translation_data = parent::getExistingTranslationData($translation_request, $langcode);
    if (!$existing_translation_data) {
      return $existing_translation_data;
    }

    // Reorder the existing translation data deltas.
    $data = $translation_request->getData();
    $this->processTranslationData($data, $existing_translation_data, $translation_request->getContentEntity()->getEntityTypeId());

    return $existing_translation_data;

  }

  /**
   * Processes the translation data recursively to use the correct delta.
   *
   * @param array $data
   *   The translation data.
   * @param array $existing_translation_data
   *   The existing translation data.
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected function processTranslationData(array $data, array &$existing_translation_data, string $entity_type_id): void {
    foreach (Element::children($data) as $key) {
      if (!isset($existing_translation_data[$key])) {
        continue;
      }

      $child = $data[$key];
      foreach (Element::children($child) as $child_key) {
        $sub_child = $child[$child_key];
        if (isset($sub_child['entity']) && $existing_translation_data[$key]) {
          $this->processTranslationData($sub_child['entity'], $existing_translation_data[$key][$child_key]['entity'], $sub_child['entity']['#entity_type']);
        }
      }

      if ($this->hasTranslationId($key, $entity_type_id)) {
        $existing_translation_data_source = $existing_translation_data[$key];
        if (!$existing_translation_data_source) {
          continue;
        }
        foreach (Element::children($data[$key]) as $delta) {
          $translation_id = $data[$key][$delta]['translation_id']['#text'];
          if (!$translation_id) {
            continue;
          }

          $delta_for_translation_id = $this->getDeltaForTranslationId($translation_id, $existing_translation_data_source);
          if (!$delta_for_translation_id) {
            // It can happen that the translation doesn't have a translation ID
            // while the source does. This is if the node was created and
            // translated before this feature and an update to the source was
            // made after the feature was deployed, hence the source having
            // a translation ID and the translation not. In this case, we do
            // nothing and maintain best effort to prevent all the fields from
            // becoming empty for no reason.
            continue;
          }

          $existing_translation_data[$key][$delta] = $delta_for_translation_id;
        }
      }
    }
  }

  /**
   * Retrieves the delta for a given translation ID.
   *
   * @param string $translation_id
   *   The translation ID.
   * @param array $existing_translation_data
   *   The existing translation data.
   *
   * @return array
   *   The corresponding translation data for a given ID.
   */
  protected function getDeltaForTranslationId(string $translation_id, array $existing_translation_data): array {
    foreach (Element::children($existing_translation_data) as $delta) {
      if ($existing_translation_data[$delta]['translation_id']['#text'] === $translation_id) {
        return $existing_translation_data[$delta];
      }
    }

    return [];
  }

  /**
   * Checks if a given field name has a translation_id property.
   */
  protected function hasTranslationId(string $field_name, string $entity_type_id): bool {
    $field_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
    $field_definition = $field_definitions[$field_name] ?? NULL;
    if (!$field_definition) {
      return FALSE;
    }

    $property_names = $field_definition->getPropertyNames();
    return in_array('translation_id', $property_names);
  }

}
