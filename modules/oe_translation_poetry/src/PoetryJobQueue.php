<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\tmgmt\JobInterface;

/**
 * Tracks the TMGMT jobs that need to be transformed into a Poetry request.
 */
class PoetryJobQueue {

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $store;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * PoetryJobQueue constructor.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $privateTempStoreFactory
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(PrivateTempStoreFactory $privateTempStoreFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->store = $privateTempStoreFactory->get('oe_translation_poetry');
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Adds a job to the queue.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   */
  public function addJob(JobInterface $job): void {
    $queue = $this->initializeQueue();

    $jobs = &$queue['jobs'];
    if (!in_array($job->id(), $jobs)) {
      $jobs[] = $job->id();
      $this->store->set('queue', $queue);
    }
  }

  /**
   * Sets the entity type and ID of the entity being translated.
   *
   * @param string $entity_type
   * @param string $id
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function setEntityId(string $entity_type, string $id): void {
    $queue = $this->initializeQueue();
    $queue['entity_type'] = $entity_type;
    $queue['entity_revision_id'] = $id;
    $this->store->set('queue', $queue);
  }

  /**
   * Returns the entity being translated.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   */
  public function getEntity(): ContentEntityInterface {
    $queue = $this->initializeQueue();
    if (!$queue['entity_revision_id']) {
      throw new \Exception('Missing Entity in the queue.');
    }

    return $this->entityTypeManager->getStorage($queue['entity_type'])->loadRevision($queue['entity_revision_id']);
  }

  /**
   * Returns all the jobs in the queue.
   *
   * @return JobInterface[]
   */
  public function getAllJobs(): array {
    $queue = $this->initializeQueue();
    if (!$queue['jobs']) {
      return [];
    }

    return $this->entityTypeManager->getStorage('tmgmt_job')->loadMultiple($queue['jobs']);
  }

  /**
   * Sets the destination URL.
   *
   * @param \Drupal\Core\Url $url
   *   The URL where the user will be redirected once the queue is processed.
   */
  public function setDestination(Url $url): void {
    $queue = $this->initializeQueue();
    $queue['destination'] = $url;
    $this->store->set('queue', $queue);
  }

  /**
   * Returns the destination URL.
   *
   * @return \Drupal\Core\Url|null
   *   The URL where the user will be redirected once the queue is processed.
   */
  public function getDestination(): ?Url {
    $queue = $this->initializeQueue();
    return $queue['destination'];
  }

  /**
   * Resets the entire queue.
   */
  public function reset(): void {
    $this->store->delete('queue');
  }

  /**
   * Initializes the queue if empty and returns the queue data.
   *
   * @return array
   *   The queue data.
   */
  protected function initializeQueue(): array {
    $queue = $this->store->get('queue');
    if (!$queue) {
      $queue = [
        'jobs' => [],
        'entity_revision_id' => NULL,
        'entity_type' => NULL,
        'destination' => NULL
      ];
      $this->store->set('queue', $queue);
    }

    return $queue;
  }
}
