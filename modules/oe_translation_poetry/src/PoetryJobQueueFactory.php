<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Factory service for job queues.
 */
class PoetryJobQueueFactory {

  /**
   * The local queues.
   *
   * @var \Drupal\oe_translation_poetry\PoetryJobQueue[]
   */
  protected $queues = [];

  /**
   * The private temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $privateTempStoreFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * PoetryJobQueueFactory constructor.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $privateTempStoreFactory
   *   The private temp store.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(PrivateTempStoreFactory $privateTempStoreFactory, EntityTypeManagerInterface $entityTypeManager, LanguageManagerInterface $languageManager) {
    $this->privateTempStoreFactory = $privateTempStoreFactory;
    $this->entityTypeManager = $entityTypeManager;
    $this->languageManager = $languageManager;
  }

  /**
   * Creates a job queue for an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\oe_translation_poetry\PoetryJobQueue
   *   The job queue.
   */
  public function get(ContentEntityInterface $entity): PoetryJobQueue {
    $key = "{$entity->getEntityTypeId()}:{$entity->id()}";
    if (isset($this->queues[$key])) {
      return $this->queues[$key];
    }

    $store = $this->privateTempStoreFactory->get('oe_translation_poetry_queue');
    $queue = new PoetryJobQueue($store, $key, $this->entityTypeManager, $this->languageManager);

    $this->queues[$key] = $queue;
    return $this->queues[$key];
  }

  /**
   * Access handler for routes that depend on the job queue.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Entity\ContentEntityInterface $node
   *   The node entity.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(AccountInterface $account, ContentEntityInterface $node): AccessResultInterface {
    $queue = $this->get($node);
    return AccessResult::allowedIf(!empty($queue->getAllJobs()))
      ->cachePerUser()
      ->addCacheTags(['tmgmt_job_list']);
  }

}
