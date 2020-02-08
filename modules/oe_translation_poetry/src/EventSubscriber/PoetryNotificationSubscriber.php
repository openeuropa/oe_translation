<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\oe_translation_poetry\Plugin\tmgmt\Translator\PoetryTranslator;
use Drupal\oe_translation_poetry_html_formatter\PoetryContentFormatterInterface;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\JobItemInterface;
use EC\Poetry\Events\Notifications\StatusUpdatedEvent;
use EC\Poetry\Events\Notifications\TranslationReceivedEvent;
use EC\Poetry\Messages\Components\Identifier;
use EC\Poetry\Messages\Components\Status;
use EC\Poetry\Messages\Components\Target;
use EC\Poetry\Messages\Notifications\StatusUpdated;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Poetry notification subscriber.
 *
 * This is not a typical Drupal event subscriber but one that is manually
 * registered in the Poetry library event dispatcher.
 */
class PoetryNotificationSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The content formatter.
   *
   * @var \Drupal\oe_translation_poetry_html_formatter\PoetryContentFormatterInterface
   */
  protected $contentFormatter;

  /**
   * Constructs a PoetryNotificationSubscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger.
   * @param \Drupal\oe_translation_poetry_html_formatter\PoetryContentFormatterInterface $contentFormatter
   *   The content formatter.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $loggerChannelFactory, PoetryContentFormatterInterface $contentFormatter) {
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $loggerChannelFactory->get('poetry');
    $this->contentFormatter = $contentFormatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      TranslationReceivedEvent::NAME => 'onTranslationReceivedEvent',
      StatusUpdatedEvent::NAME => 'onStatusUpdatedEvent',
    ];
  }

  /**
   * Notification handler for when a translation is received.
   *
   * @param \EC\Poetry\Events\Notifications\TranslationReceivedEvent $event
   *   Event object.
   */
  public function onTranslationReceivedEvent(TranslationReceivedEvent $event): void {
    /** @var \EC\Poetry\Messages\Notifications\TranslationReceived $message */
    $message = $event->getMessage();
    $identifier = $message->getIdentifier();

    // Load the jobs of the identifier.
    $jobs = $this->getJobsForIdentifier($identifier);
    if (!$jobs) {
      $this->logger->error('Translation notification received but no corresponding jobs found: @id', ['@id' => $identifier->getFormattedIdentifier()]);
      return;
    }

    foreach ($message->getTargets() as $target) {
      $language = strtolower($target->getLanguage());
      /** @var \Drupal\tmgmt\JobInterface $job */
      $job = $jobs[$language] ?? NULL;
      if (!$job) {
        $this->logger->error('Translation to @language received for job but job cannot be found: @id', [
          '@language' => $language,
          '@id' => $identifier->getFormattedIdentifier(),
        ]);
        continue;
      }

      // Get item from job (we expect only one job item (entity).
      $job_item = current($job->getItems());
      if (!$job_item instanceof JobItemInterface) {
        $this->logger->error('Translation to @language received but the job item could not be retrieved: @id. Job ID: @job_id.', [
          '@language' => $language,
          '@id' => $identifier->getFormattedIdentifier(),
          '@job_id' => $job->id(),
        ]);
        continue;
      }

      $data = $target->getTranslatedFile();
      $decoded = base64_decode($data);
      $values = $this->contentFormatter->import($decoded, FALSE);
      if (!$values) {
        $this->logger->error('Translation to @language received but the values could not be read: @id. Job ID: @job_id.', [
          '@language' => $language,
          '@id' => $identifier->getFormattedIdentifier(),
          '@job_id' => $job->id(),
        ]);
        continue;
      }

      $item_values = reset($values);
      $job_item->addTranslatedData($item_values, [], TMGMT_DATA_ITEM_STATE_TRANSLATED);

      $this->changeJobState($job, PoetryTranslator::POETRY_STATUS_TRANSLATED, 'poetry_state', 'Poetry has translated this job.');
      $job->save();
    }
  }

  /**
   * Notification handler for when a status message arrives.
   *
   * @param \EC\Poetry\Events\Notifications\StatusUpdatedEvent $event
   *   Event object.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function onStatusUpdatedEvent(StatusUpdatedEvent $event): void {
    /** @var \EC\Poetry\Messages\Notifications\StatusUpdated $message */
    $message = $event->getMessage();
    $identifier = $message->getIdentifier();

    // Load the jobs of the identifier.
    $jobs = $this->getJobsForIdentifier($identifier);

    if (!$jobs) {
      $this->logger->error('Status update notification received but no corresponding jobs found: @id', ['@id' => $identifier->getFormattedIdentifier()]);
      return;
    }

    // Update the accepted date for each language.
    foreach ($message->getTargets() as $target) {
      $language = strtolower($target->getLanguage());
      $job = $jobs[$language] ?? NULL;
      if (!$job) {
        $this->logger->error('There is a missing job for the language @lang and request ID @id', ['@lang' => $language, '@id' => $identifier->getFormattedIdentifier()]);
        continue;
      }

      // Set the date that comes from Poetry.
      $this->updateJobDate($job, $target);
    }

    $statuses = $message->getStatuses();
    $attributions = [];
    foreach ($statuses as $status) {
      if ($status->getType() === 'attribution') {
        $attributions[strtolower($status->getLanguage())] = $status;
      }
    }

    // Get the demand status and see if we should accept the translation jobs
    // or not.
    $status = $message->getDemandStatus();
    foreach ($jobs as $language => $job) {
      if (!isset($attributions[$language])) {
        // In case a notification comes about only some of the jobs with the
        // same identifier, we do nothing for the jobs not included.
        continue;
      }

      // The entire request has been rejected or cancelled.
      if (in_array($status->getCode(), ['CNL', 'REF'])) {
        $this->rejectJob($job, 'Poetry has rejected the entire translation request: @message', ['@message' => $status->getMessage()]);
        continue;
      }

      // By default, the job gets the status from the main request.
      $job_status_code = $status->getCode();
      $attribution_status = $this->getAttributionStatusForLanguage($language, $message);
      if ($attribution_status) {
        // However, individual languages within an accepted request can be
        // refused.
        $job_status_code = $attribution_status->getCode();
      }

      if ($job_status_code === 'ONG') {
        $this->changeJobState($job, PoetryTranslator::POETRY_STATUS_ONGOING, 'poetry_state', 'Poetry has marked this job as ongoing.');
        continue;
      }

      if (in_array($job_status_code, ['CNL', 'REF'])) {
        $this->rejectJob($job, 'Poetry has rejected the translation job for this language: @message', ['@message' => $attribution_status->getMessage()]);
        continue;
      }

      $job->addMessage('Poetry sent a status of @status for this job: @message', ['@status' => $status->getCode(), '@message' => $status->getMessage()]);
    }

    // Save all the jobs in case they were changed.
    foreach ($jobs as $job) {
      $job->save();
    }
  }

  /**
   * Queries and returns the jobs for a given identifier.
   *
   * Return array is keyed by remote mapped language.
   *
   * @param \EC\Poetry\Messages\Components\Identifier $identifier
   *   The identifier.
   *
   * @return \Drupal\tmgmt\JobInterface[]
   *   The jobs.
   */
  protected function getJobsForIdentifier(Identifier $identifier): array {
    $ids = $this->entityTypeManager->getStorage('tmgmt_job')->getQuery()
      ->condition('poetry_request_id.code', $identifier->getCode())
      ->condition('poetry_request_id.year', $identifier->getYear())
      ->condition('poetry_request_id.number', $identifier->getNumber())
      ->condition('poetry_request_id.version', $identifier->getVersion())
      ->condition('poetry_request_id.part', $identifier->getPart())
      ->condition('poetry_request_id.product', $identifier->getProduct())
      ->condition('state', Job::STATE_ACTIVE)
      ->execute();

    if (!$ids) {
      return [];
    }

    $jobs = [];
    /** @var \Drupal\tmgmt\JobInterface[] $entities */
    $entities = $this->entityTypeManager->getStorage('tmgmt_job')->loadMultiple($ids);
    foreach ($entities as $entity) {
      $jobs[$entity->getRemoteTargetLanguage()] = $entity;
    }

    return $jobs;
  }

  /**
   * Changes a job state and sets a message if there is an actual change.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The job.
   * @param string|int $state
   *   The state.
   * @param string $field
   *   The field for setting the state.
   * @param string $message
   *   The message.
   * @param array $variables
   *   The variables to use in the message.
   */
  protected function changeJobState(JobInterface $job, $state, string $field = 'state', string $message = '', array $variables = []): void {
    $existing_state = $job->get($field)->value;

    $job->set($field, $state);
    if ($message === '') {
      return;
    }

    if ($existing_state !== $state) {
      $job->addMessage($message, $variables);
    }
  }

  /**
   * Rejects a job.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The job.
   * @param string $message
   *   The message to log.
   * @param array $variables
   *   The message variables.
   */
  protected function rejectJob(JobInterface $job, string $message = '', array $variables = []): void {
    if ($job->isAborted()) {
      return;
    }

    $job->set('poetry_state', PoetryTranslator::POETRY_STATUS_CANCELLED);
    $job->set('poetry_request_date_updated', NULL);
    $job->aborted($message, $variables, 'status');
  }

  /**
   * Update the date on the job.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The job.
   * @param \EC\Poetry\Messages\Components\Target $target
   *   The message target.
   */
  protected function updateJobDate(JobInterface $job, Target $target): void {
    $existing_date = $job->get('poetry_request_date_updated')->date ? $job->get('poetry_request_date_updated')->date->getTimestamp() : NULL;
    $date = \DateTime::createFromFormat('d/m/Y H:i', trim($target->getAcceptedDelay()));
    if (!$date) {
      $this->logger->error('The Poetry request accepted date for job @job is incorrectly formatted: @delay', ['@job' => $job->id(), '@delay' => $target->getAcceptedDelay()]);
      return;
    }

    $job->set('poetry_request_date_updated', $date->format('Y-m-d\TH:i:s'));
    if ($existing_date !== $date->getTimestamp()) {
      $job->addMessage('Poetry has updated the date on the job to @date.', ['@date' => $target->getAcceptedDelay()]);
    }
  }

  /**
   * Extracts the attribution for a specific language from the message.
   *
   * @param string $language
   *   The language.
   * @param \EC\Poetry\Messages\Notifications\StatusUpdated $message
   *   The message.
   *
   * @return \EC\Poetry\Messages\Components\Status|null
   *   The attribution status.
   */
  protected function getAttributionStatusForLanguage(string $language, StatusUpdated $message): ?Status {
    /** @var \EC\Poetry\Messages\Components\Status[] $statuses */
    $statuses = $message->getAttributionStatuses();
    foreach ($statuses as $status) {
      if (strtolower($status->getLanguage()) === $language) {
        return $status;
      }
    }

    return NULL;
  }

}
