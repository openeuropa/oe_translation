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
use EC\Poetry\Messages\Components\Identifier;
use EC\Poetry\Poetry as PoetryLibrary;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Poetry client.
 *
 * Integrates the Poetry client library with Drupal.
 *
 * @method Identifier getIdentifier()
 * @method Server getServer()
 * @method EventDispatcherInterface getEventDispatcher()
 */
class Poetry {

  /**
   * The Poetry client library.
   *
   * @var \EC\Poetry\Poetry
   */
  protected $poetryClient;

  /**
   * The settings provided by the translator config.
   *
   * @var array
   */
  protected $translatorSettings;

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
   * @param array $settings
   *   The settings provided by the translator config.
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
   */
  public function __construct(array $settings, ConfigFactoryInterface $configFactory, LoggerChannelInterface $loggerChannel, State $state, EntityTypeManagerInterface $entityTypeManager, Connection $database, RequestStack $requestStack) {
    $this->translatorSettings = $settings;
    $this->state = $state;
    $this->entityTypeManager = $entityTypeManager;
    $this->database = $database;
    $this->loggerChannel = $loggerChannel;
    $this->requestStack = $requestStack;
    $this->configFactory = $configFactory;

    if (!isset($this->translatorSettings['site_id'])) {
      $this->translatorSettings['site_id'] = $this->configFactory->get('system.site')->get('name');
    }
  }

  /**
   * Initializes the Poetry instance.
   */
  public function initialize() {
    if ($this->poetryClient instanceof PoetryLibrary) {
      return;
    }

    $values = [
      'identifier.code' => $this->translatorSettings['identifier_code'] ?? 'WEB',
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
      'logger' => $this->loggerChannel,
      'log_level' => LogLevel::INFO,
    ];

    if (isset($this->translatorSettings['service_wsdl'])) {
      $values['service.wsdl'] = $this->translatorSettings['service_wsdl'];
    }

    $this->poetryClient = new PoetryLibrary($values);

    // Register our own service provider to override the SOAP client. We need
    // to pass the current cookies to that the SOAP client uses them in its
    // requests. This is critical for ensuring the tests work.
    $this->poetryClient->register(new PoetrySoapProvider($this->requestStack->getCurrentRequest()->cookies->all()));
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
   * Gets the global identification number.
   *
   * @return string|null
   *   The number.
   */
  public function getGlobalIdentifierNumber(): ?string {
    return $this->state->get('oe_translation_poetry_id_number');
  }

  /**
   * Sets the global identification number.
   *
   * @param string $number
   *   The number.
   */
  public function setGlobalIdentifierNumber(string $number): void {
    $this->state->set('oe_translation_poetry_id_number', $number);
  }

  /**
   * Returns the identifier for making a translation request for a content.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return \EC\Poetry\Messages\Components\Identifier
   *   The identifier.
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
   * Returns the settings configured in the translator.
   *
   * @return array
   *   The settings.
   */
  public function getTranslatorSettings(): array {
    return $this->translatorSettings;
  }

  /**
   * Locates the last identifier that was used for a given content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return \EC\Poetry\Messages\Components\Identifier
   *   The identifier.
   */
  protected function getLastIdentifierForContent(ContentEntityInterface $entity): ?Identifier {
    $query = $this->database->select('tmgmt_job', 'job');
    $query->join('tmgmt_job_item', 'job_item', 'job.tjid = job_item.tjid');
    $query->fields('job');
    $query->condition('job_item.item_id', $entity->id());
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
