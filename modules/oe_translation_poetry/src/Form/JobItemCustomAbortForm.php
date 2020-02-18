<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\tmgmt\Form\JobItemAbortForm;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\TMGMTException;

/**
 * Provides a form for deleting a node.
 */
class JobItemCustomAbortForm extends JobItemAbortForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to abort the translation item %title?', [
      '%title' => $this->entity->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('DGT will not be notified. Aborted translation items can no longer be accepted.');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\tmgmt\Entity\JobItem $entity */
    $job_item = $this->entity;
    try {
      if (!$job_item->isNeedsReview() || !$job_item->getTranslatorPlugin()) {
        throw new TMGMTException('Cannot abort job item.');
      }
      $job_item->setState(JobItemInterface::STATE_ABORTED);
      // Check if this was the last unfinished job item in this job.
      /** @var \Drupal\tmgmt\JobInterface $job */
      $job = $job_item->getJob();
      if ($job && !$job->isContinuous() && tmgmt_job_check_finished($job_item->getJobId())) {
        // Mark the job as finished in case it is a normal job.
        $job->finished($this->t('The translation job has been finished (aborted).'));
      }
    }
    catch (TMGMTException $e) {
      $this->messenger()->addError($this->t('Job item cannot be aborted: %error.', [
        '%error' => $e->getMessage(),
      ]));
    }

    tmgmt_write_request_messages($job_item);
    $this->messenger()->addStatus($this->t('The translation item has been aborted.'));

    $url = Url::fromRoute('entity.node.content_translation_overview', ['node' => $job_item->getItemId()]);
    $form_state->setRedirectUrl($url);
  }

}
