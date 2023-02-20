<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;

/**
 * RemoteTranslationProvider plugin manager.
 */
class RemoteTranslationProviderManager extends DefaultPluginManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs RemoteTranslationProviderManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct(
      'Plugin/RemoteTranslationProvider',
      $namespaces,
      $module_handler,
      'Drupal\oe_translation_remote\RemoteTranslationProviderInterface',
      'Drupal\oe_translation_remote\Annotation\RemoteTranslationProvider');

    $this->alterInfo('remote_translation_provider_info');
    $this->setCacheBackend($cache_backend, 'remote_translation_provider_plugins');
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Returns the translation request bundles used for remote translators.
   *
   * @return array
   *   The IDs of the bundles.
   */
  public function getRemoteTranslationBundles(): array {
    $types = $this->entityTypeManager->getStorage('oe_translation_request_type')->getQuery()
      ->condition('third_party_settings.oe_translation_remote.remote_bundle', TRUE)
      ->execute();

    return array_values($types);
  }

  /**
   * Returns the existing translation requests for a given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param bool $revision
   *   Whether to restrict to translation requests for the exact revision.
   * @param array $statuses
   *   Do not include requests with these statuses.
   *
   * @return array
   *   The translation requests.
   */
  public function getExistingTranslationRequests(ContentEntityInterface $entity, bool $revision = TRUE, array $statuses = []): array {
    // Determine which are the bundles used for remote translators.
    $bundles = $this->getRemoteTranslationBundles();
    if (!$bundles) {
      return [];
    }

    $query = $this->entityTypeManager->getStorage('oe_translation_request')->getQuery()
      ->condition('content_entity__entity_type', $entity->getEntityTypeId())
      ->condition('content_entity__entity_id', $entity->id());

    if ($revision) {
      $query->condition('content_entity__entity_revision_id', $entity->getRevisionId());
    }

    $group = $query->orConditionGroup();

    foreach ($bundles as $bundle) {
      $condition = $query->andConditionGroup();
      $condition->condition('bundle', $bundle);
      if (!$statuses) {
        $statuses = [
          TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED,
          TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED_FINISHED,
        ];
      }

      $condition->condition('request_status', $statuses, 'NOT IN');
      $group->condition($condition);
    }

    $query->condition($group);
    $ids = $query->execute();

    if (!$ids) {
      return [];
    }

    return $this->entityTypeManager->getStorage('oe_translation_request')->loadMultiple($ids);
  }

}
