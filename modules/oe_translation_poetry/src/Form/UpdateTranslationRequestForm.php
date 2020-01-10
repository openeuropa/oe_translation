<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tmgmt\Entity\JobItem;

/**
 * Form for requesting translations updates.
 */
class UpdateTranslationRequestForm extends NewTranslationRequestForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_translation_poetry_update_translation_request';
  }

  /**
   * Returns the title of the form page.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $node
   *   The node entity to use when generating the title.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title for the form page.
   */
  public function getPageTitle(ContentEntityInterface $node = NULL): TranslatableMarkup {
    $queue = $this->queueFactory->get($node);
    $entity = $queue->getEntity();
    $target_languages = $queue->getTargetLanguages();
    $target_languages = implode(', ', $target_languages);
    return $this->t('Send update request to DG Translation for <em>@entity</em> in <em>@target_languages</em>', ['@entity' => $entity->label(), '@target_languages' => $target_languages]);
  }

  /**
   * Submits the request to Poetry.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitRequest(array &$form, FormStateInterface $form_state): void {
    $entity = $form_state->get('entity');
    $queue = $this->queueFactory->get($entity);
    $queued_jobs = $queue->getAllJobsIds();

    parent::submitRequest($form, $form_state);

    // Abort non queued active jobs for this entity.
    $jobs = $this->getActiveJobsForEntity($entity);
    foreach ($jobs as $job) {
      if (!in_array($job->id(), $queued_jobs)) {
        $job->aborted('Job aborted after update request.');
      }
    }
  }

  /**
   * Get active jobs of an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\tmgmt\JobInterface[]
   *   The active jobs.
   */
  protected function getActiveJobsForEntity(EntityInterface $entity): array {
    $ids = $this->entityTypeManager->getStorage('tmgmt_job_item')->getQuery()
      ->condition('item_type', $entity->getEntityType()->id())
      ->condition('item_id', $entity->id())
      ->condition('state', JobItem::STATE_ACTIVE)
      ->execute();

    if (!$ids) {
      return [];
    }

    $jobs = [];
    /** @var \Drupal\tmgmt\JobItemInterface[] $job_items */
    $job_items = $this->entityTypeManager->getStorage('tmgmt_job_item')->loadMultiple($ids);
    foreach ($job_items as $job_item) {
      $job = $job_item->getJob();
      if ($job->getTranslatorId() !== 'poetry') {
        continue;
      }
      $jobs[] = $job;
    }

    return $jobs;
  }

}
