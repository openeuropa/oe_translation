<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\tmgmt\Form\JobItemAbortForm as OriginalJobItemAbortForm;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt\TMGMTException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for cancelling a translation job item.
 *
 * Currently not used, we should reintroduce once we clarify the impact on
 * aborting local jobs items.
 */
class JobItemAbortForm extends OriginalJobItemAbortForm {

  /**
   * Constructs a JobItemAbortForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, MessengerInterface $messenger, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('messenger'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    /** @var \Drupal\tmgmt\JobItemInterface $job_item */
    $job_item = $this->entity;
    $content_entity = $this->entityTypeManager->getStorage($job_item->getItemType())->load($job_item->getItemId());
    $job = $job_item->getJob();
    if (!$content_entity instanceof ContentEntityInterface) {
      return $this->t('Are you sure you want to cancel this translation in %language? The source content entity is missing.', [
        '%language' => $job->getTargetLanguage()->getName(),
      ]);
    }

    return $this->t('Are you sure you want to cancel the translation for %title in %language?', [
      '%title' => $content_entity->label(),
      '%language' => $job->getTargetLanguage()->getName(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Please be aware that DGT will not be notified and cancelled translations can no longer be accepted or resent from DGT without making a brand new request.');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\tmgmt\JobItemInterface $job_item */
    $job_item = $this->entity;
    try {
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
      $this->messenger->addError($this->t('The translation could not be cancelled: %error.', [
        '%error' => $e->getMessage(),
      ]));
    }

    $this->messenger->addStatus($this->t('The translation has been cancelled.'));

    $url = Url::fromRoute('entity.node.content_translation_overview', ['node' => $job_item->getItemId()]);
    $form_state->setRedirectUrl($url);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    /** @var \Drupal\tmgmt\JobItemInterface $job_item */
    $job_item = $this->entity;
    if (!$job_item->getTranslatorPlugin()) {
      $form_state->setErrorByName('description', $this->t('Cannot cancel the translation as it is missing a translator.'));
      return;
    }

    if (!$job_item->isNeedsReview()) {
      $form_state->setErrorByName('description', $this->t('Cannot cancel the translation because it is not up for review.'));
    }
  }

}
