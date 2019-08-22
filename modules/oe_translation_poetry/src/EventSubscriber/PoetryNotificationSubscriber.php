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
      $job = $jobs[$language] ?? NULL;

      if (!$job) {
        $this->logger->error('Translation to @language received for job but job cannot be found: @id', ['@language' => $language, '@id' => $identifier->getFormattedIdentifier()]);
        continue;
      }

      $data = $target->getTranslatedFile();
      $decoded = base64_decode($data);
      $values = $this->contentFormatter->import($decoded, FALSE);
      if (!$values) {
        $this->logger->error('Translation notification received but the values could not be read: @id', ['@id' => $identifier->getFormattedIdentifier()]);
        return;
      }

      // We expect only one job item (entity) values to be translated.
      $item_values = reset($values);
      $job_item_id = key($values);
      $items = $job->getItems();
      $item = $items[$job_item_id] ?? NULL;
      if (!$item instanceof JobItemInterface) {
        $this->logger->error('Translation notification received but the job ID could not be retrieved: @id. Job Item ID: @job_item.', ['@id' => $identifier->getFormattedIdentifier(), '@job_item_id' => $job_item_id]);
        return;
      }

      $item->addTranslatedData($item_values, [], TMGMT_DATA_ITEM_STATE_TRANSLATED);
      $job->addMessage('The translation has been received from Poetry and saved on the Job.');
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
      /** @var \Drupal\tmgmt\JobInterface $job */
      $job = $jobs[$language] ?? NULL;
      if (!$job) {
        $this->logger->error('There is a missing job for the language @lang and request ID @id', ['@lang' => $language, '@id' => $identifier->getFormattedIdentifier()]);
        continue;
      }

      // Set the date that comes from Poetry.
      $this->updateJobDate($job, $target);
    }

    // Get the demand status and see if we should accept the translation jobs
    // or not.
    $status = $message->getDemandStatus();
    foreach ($jobs as $language => $job) {
      // The request has been accepted.
      if ($status->getCode() === 'ONG') {
        // The entire request details (demande) status can be accepted at once
        // so we update all the jobs.
        $this->changeJobState($job, PoetryTranslator::POETRY_STATUS_ONGOING, 'poetry_state', 'Poetry has marked this job as ongoing.');

        // However, for each individual language, we get a separate status
        // update (attribution) to cancel the translation job.
        $attribution_status = $this->getAttributionStatusForLanguage($language, $message);
        if (!$attribution_status) {
          continue;
        }

        if ($attribution_status->getCode() === 'ONG') {
          // We already set this job as ongoing so we don't have to do anything.
          continue;
        }

        // Otherwise, we check to see if we need to reject this individual job.
        if (in_array($attribution_status->getCode(), ['CNL', 'REF'])) {
          $this->changeJobState($job, Job::STATE_REJECTED, 'state', 'Poetry has rejected the translation job for this language: @message', ['@message' => $attribution_status->getMessage()]);
        }

        continue;
      }

      // The entire request has been rejected or cancelled.
      if (in_array($status->getCode(), ['CNL', 'REF'])) {
        $this->changeJobState($job, Job::STATE_REJECTED, 'state', 'Poetry has rejected the entire translation request: @message', ['@message' => $status->getMessage()]);
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
   * Queries and returns the jobs for a given identifier, keyed by language.
   *
   * @param \EC\Poetry\Messages\Components\Identifier $identifier
   *   The identifier.
   *
   * @return \Drupal\tmgmt\Entity\JobInterface[]
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
      $jobs[$entity->getTargetLangcode()] = $entity;
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
      $job->addMessage('Poetry has updated the date on the job to @date.', ['@date' => $target->getDelay()]);
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
