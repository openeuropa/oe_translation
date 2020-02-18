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
  public function JobItemAbortFormSubmit(array &$form, FormStateInterface $form_state) {

    /* @var JobItemInterface $job_item */
    $job_item = $form_state->getFormObject()->getEntity();

    try {
      if ((!$job_item->isActive() && !$job_item->isNeedsReview()) || !$job_item->getTranslatorPlugin()) {
        throw new TMGMTException('Cannot abort job item.');
      }
      $job_item->setState(JobItemInterface::STATE_ABORTED);
      // Check if this was the last unfinished job item in this job.
      $job = $job_item->getJob();
      if ($job && !$job->isContinuous() && tmgmt_job_check_finished($job_item->getJobId())) {
        // Mark the job as finished in case it is a normal job.
        $job->finished();
      }
    }
    catch(TMGMTException $e) {
      \Drupal::messenger()->addError(t('Job item cannot be aborted: %error.', array(
        '%error' => $e->getMessage(),
      )));
      return;
    }

    tmgmt_write_request_messages($job_item);
    \Drupal::messenger()->addStatus(t('The translation has been aborted.'));

    $url = Url::fromRoute('entity.node.content_translation_overview', ['node' => $job_item->getItemId()]);
    $form_state->setRedirectUrl($url);
  }

}
