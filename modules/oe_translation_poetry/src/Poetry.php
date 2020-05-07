<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\State;
use Drupal\Core\Url;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\TranslatorInterface;
use EC\Poetry\Messages\Components\Identifier;
use EC\Poetry\Poetry as PoetryLibrary;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Poetry client.
 *
 * Integrates the Poetry client library with Drupal.
 *
 * @method \EC\Poetry\Messages\Components\Identifier getIdentifier()
 * @method \EC\Poetry\Server getServer()
 * @method \EC\Poetry\Client getClient()
 * @method \EC\Poetry\Services\Settings getSettings()
 * @method \Symfony\Component\EventDispatcher\EventDispatcherInterface getEventDispatcher()
 * @method get()
 */
class Poetry implements PoetryInterface {

  /**
   * The Poetry client library.
   *
   * @var \EC\Poetry\Poetry
   */
  protected $poetryClient;

  /**
   * The translator being used.
   *
   * @var \Drupal\tmgmt\TranslatorInterface
   */
  protected $translator;

  /**
   * The state.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a Poetry instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $loggerChannel
   *   The logger channel.
   * @param \Drupal\Core\State\State $state
   *   The state.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\tmgmt\TranslatorInterface|null $translator
   *   The translator being used.
   */
  public function __construct(ConfigFactoryInterface $configFactory, LoggerChannelInterface $loggerChannel, State $state, EntityTypeManagerInterface $entityTypeManager, Connection $database, RequestStack $requestStack, TranslatorInterface $translator = NULL) {
    $this->translator = $translator;
    $this->state = $state;
    $this->entityTypeManager = $entityTypeManager;
    $this->database = $database;
    $this->loggerChannel = $loggerChannel;
    $this->requestStack = $requestStack;
    $this->configFactory = $configFactory;
  }

  /**
   * Initializes the Poetry instance.
   */
  public function initialize(): void {
    if ($this->poetryClient instanceof PoetryLibrary) {
      return;
    }
    $translator_settings = $this->getTranslatorSettings();
    $values = [
      'identifier.code' => $translator_settings['identifier_code'] ?? 'WEB',
      // The default version will always start from 0.
      'identifier.version' => 0,
      // The default part will always start from 0.
      'identifier.part' => 0,
      'identifier.year' => date('Y'),
      'service.username' => Settings::get('poetry.service.username'),
      'service.password' => Settings::get('poetry.service.password'),
      'notification.username' => Settings::get('poetry.notification.username'),
      'notification.password' => Settings::get('poetry.notification.password'),
      'notification.endpoint' => Url::fromRoute('oe_translation_poetry.notifications')->setAbsolute()->toString(),
      'service.wsdl' => Settings::get('poetry.service.endpoint'),
      'logger' => $this->loggerChannel,
      'log_level' => LogLevel::INFO,
    ];

    $this->poetryClient = new PoetryLibrary($values);
  }

  /**
   * Delegates to the Poetry library all calls made to this service.
   *
   * {@inheritdoc}
   */
  public function __call($name, $arguments) {
    $this->initialize();
    if (method_exists($this->poetryClient, $name)) {
      return call_user_func_array([$this->poetryClient, $name], $arguments);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getGlobalIdentifierNumber(): ?string {
    return $this->state->get('oe_translation_poetry_id_number');
  }

  /**
   * {@inheritdoc}
   */
  public function setGlobalIdentifierNumber(string $number): void {
    $this->state->set('oe_translation_poetry_id_number', $number);
  }

  /**
   * {@inheritdoc}
   */
  public function isNewIdentifierNumberRequired(): bool {
    return $this->state->get('oe_translation_poetry_number_reset') ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function forceNewIdentifierNumber($force_new_number): void {
    $this->state->set('oe_translation_poetry_number_reset', $force_new_number);
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentifierForContent(ContentEntityInterface $entity): Identifier {

    $last_identifier_for_content = $this->getLastIdentifierForContent($entity);
    if ($last_identifier_for_content instanceof Identifier) {
      // If the content has already been translated, we need to use the
      // identifier it had and just increase the version. This means potentially
      // even an older number than the most current global one as well as an
      // older year.
      $last_identifier_for_content->setVersion($last_identifier_for_content->getVersion() + 1);
      return $last_identifier_for_content;
    }

    $identifier = $this->getIdentifier();
    $number = $this->getGlobalIdentifierNumber();
    if ($this->isNewIdentifierNumberRequired()) {
      // If a new identifier number has been required, we unset the current
      // number and reset the translator configuration.
      $number = FALSE;
      $this->forceNewIdentifierNumber(FALSE);
      $this->translator->set('number_reset', FALSE);
      $this->translator->save();
    }
    if (!$number) {
      // If we don't have a number it means it's the first ever request.
      $identifier->setSequence(Settings::get('poetry.identifier.sequence'));
      return $identifier;
    }

    // If we have a global number, we can maybe use it. However, we first to
    // determine the part. And for this we need to check the jobs.
    $part = $this->getLastPartForNumber($number);
    if ($part > -1) {
      // We check if the part came back as -1 in case jobs were missing from
      // the system, we increment only if we know where to increment from.
      $part++;
    }

    // If the incremented part is 100, we need to scrap the the global number
    // and request a new one. The maximum can be 99.
    if ($part === 100) {
      $identifier->setSequence(Settings::get('poetry.identifier.sequence'));
      return $identifier;
    }

    if ($part === -1) {
      // In case we lost track of the jobs we need to reset and request a new
      // number.
      $identifier->setSequence(Settings::get('poetry.identifier.sequence'));
      return $identifier;
    }

    $identifier->setPart($part);
    $identifier->setNumber($number);

    return $identifier;
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslatorSettings(): array {
    $settings = [];
    if ($this->translator) {
      $settings = $this->translator->getSettings();
    }
    if (!isset($settings['site_id'])) {
      $settings['site_id'] = $this->configFactory->get('system.site')->get('name');
    }
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    // Poetry is not available if configuration is missing.
    $required = [
      'identifier_code',
      'title_prefix',
      'application_reference',
      'site_id',
    ];
    $translator_settings = $this->getTranslatorSettings();
    foreach ($required as $setting) {
      if (!isset($translator_settings[$setting]) || !$translator_settings[$setting]) {
        return FALSE;
      }
    }

    // Poetry is not available if environment variables are missing.
    $required = [
      'poetry.service.username',
      'poetry.service.password',
      'poetry.service.endpoint',
      'poetry.notification.username',
      'poetry.notification.password',
      'poetry.identifier.sequence',
    ];

    foreach ($required as $setting) {
      $value = Settings::get($setting);
      if (!$value || $value === "") {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastIdentifierForContent(ContentEntityInterface $entity): ?Identifier {
    $query = $this->database->select('tmgmt_job', 'job');
    $query->join('tmgmt_job_item', 'job_item', 'job.tjid = job_item.tjid');
    $query->fields('job');
    $query->condition('job_item.item_id', $entity->id());
    $query->condition('job_item.item_type', $entity->getEntityTypeId());
    $query->condition('job.translator', 'poetry');
    // Do not include unprocessed Jobs. These are the ones which have not been
    // ever sent to Poetry.
    $query->condition('job.state', Job::STATE_UNPROCESSED, '!=');
    $query->range(0, 1);
    $query->orderBy('job.poetry_request_id__version', 'DESC');
    $result = $query->execute()->fetchCol('poetry_request_id__version');
    if (!$result) {
      return NULL;
    }

    $job = $this->entityTypeManager->getStorage('tmgmt_job')->load(reset($result));

    $identifier = new Identifier();
    $identifier->withArray($job->get('poetry_request_id')->first()->getValue());
    return $identifier;
  }

  /**
   * Gets the next part to use for a global number.
   *
   * @param string $number
   *   The number.
   *
   * @return int
   *   The part.
   */
  protected function getLastPartForNumber(string $number): int {
    $job_ids = $this->entityTypeManager->getStorage('tmgmt_job')->getQuery()
      ->condition('poetry_request_id__number', $number)
      ->sort('poetry_request_id.part', 'DESC')
      ->range(0, 1)
      ->execute();

    if (!$job_ids) {
      // Normally we should get a value since the number must have been used
      // on previous jobs.
      return -1;
    }

    /** @var \Drupal\tmgmt\JobInterface $job */
    $job = $this->entityTypeManager->getStorage('tmgmt_job')->load(reset($job_ids));
    return (int) $job->get('poetry_request_id')->part;
  }

}
