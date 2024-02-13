<?php

declare(strict_types=1);

namespace Drupal\oe_translation;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_translation\Entity\TranslationRequestType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic permissions for different translation request types.
 */
class TranslationRequestPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a TranslationRequestPermissions instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Returns an array of translation request permissions.
   */
  public function translationRequestTypePermissions() {
    $permissions = [];
    // Generate permissions for all translation request types.
    foreach ($this->entityTypeManager->getStorage('oe_translation_request_type')->loadMultiple() as $bundle) {
      $permissions += $this->buildPermissions($bundle);
    }

    return $permissions;
  }

  /**
   * Returns a list of permissions for a given translation request type.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestType $type
   *   The translation request type.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildPermissions(TranslationRequestType $type) {
    $type_id = $type->id();
    $type_params = ['%type_name' => $type->label()];

    return [
      "create $type_id translation request" => [
        'title' => $this->t('Create new %type_name translation request', $type_params),
      ],
      "edit $type_id translation request" => [
        'title' => $this->t('Edit any %type_name translation request', $type_params),
      ],
      "delete $type_id translation request" => [
        'title' => $this->t('Delete any %type_name translation request', $type_params),
      ],
    ];
  }

}
