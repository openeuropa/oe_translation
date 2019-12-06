<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\oe_translation_poetry\Poetry;
use Drupal\oe_translation_poetry\PoetryJobQueueFactory;
use Drupal\oe_translation_poetry_html_formatter\PoetryContentFormatterInterface;
use Drupal\tmgmt\Entity\JobItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for requesting translations updates.
 */
class UpdateTranslationRequestForm extends NewTranslationRequestForm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * PoetryCheckoutForm constructor.
   *
   * @param \Drupal\oe_translation_poetry\PoetryJobQueueFactory $queueFactory
   *   The job queue factory.
   * @param \Drupal\oe_translation_poetry\Poetry $poetry
   *   The Poetry client.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\oe_translation_poetry_html_formatter\PoetryContentFormatterInterface $contentFormatter
   *   The content formatter.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(PoetryJobQueueFactory $queueFactory, Poetry $poetry, MessengerInterface $messenger, PoetryContentFormatterInterface $contentFormatter, LoggerChannelFactoryInterface $loggerChannelFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->queueFactory = $queueFactory;
    $this->poetry = $poetry;
    $this->messenger = $messenger;
    $this->contentFormatter = $contentFormatter;
    $this->logger = $loggerChannelFactory->get('oe_translation_poetry');
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_translation_poetry.job_queue_factory'),
      $container->get('oe_translation_poetry.client.default'),
      $container->get('messenger'),
      $container->get('oe_translation_poetry.html_formatter'),
      $container->get('logger.factory'),
      $container->get('entity_type.manager')
    );
  }

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

    // Abort active jobs for this entity.
    $jobs = $this->getAllJobsForEntity($entity);
    foreach ($jobs as $job) {
      if (!in_array($job->id(), $queued_jobs)) {
        $job->aborted('Job aborted after update request.');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequestOperation(): string {
    return 'INSERT';
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
  protected function getAllJobsForEntity(EntityInterface $entity): array {
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
      $jobs[] = $job_item->getJob();
    }

    return $jobs;
  }

}
