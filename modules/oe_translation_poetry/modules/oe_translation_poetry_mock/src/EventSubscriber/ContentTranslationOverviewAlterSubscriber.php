<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry_mock\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\oe_translation\Event\ContentTranslationOverviewAlterEvent;
use Drupal\tmgmt\Entity\Job;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the event used for altering the content translation overview.
 */
class ContentTranslationOverviewAlterSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a ContentTranslationOverviewAlterSubscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, Connection $database, LanguageManagerInterface $languageManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->database = $database;
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ContentTranslationOverviewAlterEvent::NAME => 'alterOverview',
    ];
  }

  /**
   * Alters the content translation overview.
   *
   * @param \Drupal\oe_translation\Event\ContentTranslationOverviewAlterEvent $event
   *   The event.
   */
  public function alterOverview(ContentTranslationOverviewAlterEvent $event): void {
    $build = $event->getBuild();
    $route_match = $event->getRouteMatch();
    $entity = $route_match->getParameter($event->getEntityTypeId());

    $destination = $entity->toUrl('drupal:content-translation-overview');
    $submitted_jobs = $this->getJobsByState($entity, Job::STATE_ACTIVE);
    $ongoing_jobs = $this->getJobsByState($entity, Job::STATE_ACTIVE, 'ongoing');
    $translated_jobs = $this->getJobsByState($entity, Job::STATE_ACTIVE, 'translated');

    $languages = $this->languageManager->getLanguages();
    foreach ($languages as $langcode => $language) {
      $links = &$build['languages']['#options'][$langcode][4]['data']['#links'];
      if (!$links) {
        $links = [];
      }
      $url_options = [
        'language' => $language,
        'query' => ['destination' => $destination->toString()],
      ];

      if (isset($submitted_jobs[$language->getId()])) {
        $links['accept_job'] = [
          'title' => $this->t('Accept job (mock)'),
          'url' => Url::fromRoute('oe_translation_poetry_mock.send_status_notification', [
            'tmgmt_job' => $submitted_jobs[$language->getId()]->tjid,
            'status' => 'ONG',
          ],
            $url_options
          ),
        ];

        $links['refuse_job'] = [
          'title' => $this->t('Refuse job (mock)'),
          'url' => Url::fromRoute('oe_translation_poetry_mock.send_status_notification', [
            'tmgmt_job' => $submitted_jobs[$language->getId()]->tjid,
            'status' => 'REF',
          ],
            $url_options
          ),
        ];
      }

      if (isset($ongoing_jobs[$language->getId()])) {
        $links['translate_job'] = [
          'title' => $this->t('Translate job (mock)'),
          'url' => Url::fromRoute('oe_translation_poetry_mock.send_translation_notification', [
            'tmgmt_job' => $ongoing_jobs[$language->getId()]->tjid,
          ],
            $url_options
          ),
        ];

        $links['cancel_job'] = [
          'title' => $this->t('Cancel job (mock)'),
          'url' => Url::fromRoute('oe_translation_poetry_mock.send_status_notification', [
            'tmgmt_job' => $ongoing_jobs[$language->getId()]->tjid,
            'status' => 'CNL',
          ],
            $url_options
          ),
        ];
      }
      if (isset($translated_jobs[$language->getId()])) {
        $links['update_translation'] = [
          'title' => $this->t('Update translation (mock)'),
          'url' => Url::fromRoute('oe_translation_poetry_mock.send_translation_notification', [
            'tmgmt_job' => $translated_jobs[$language->getId()]->tjid,
          ],
            $url_options
          ),
        ];
      }
    }

    $event->setBuild($build);
  }

  /**
   * Get a list of jobs by state.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to look jobs for.
   * @param int $state
   *   The state of the job.
   * @param string $poetry_state
   *   The poetry state.
   *
   * @return array
   *   An array of unprocessed job IDs, keyed by the target language.
   */
  protected function getJobsByState(ContentEntityInterface $entity, int $state, string $poetry_state = NULL): array {
    $query = $this->database->select('tmgmt_job', 'job');
    $query->join('tmgmt_job_item', 'job_item', 'job.tjid = job_item.tjid');
    $query->fields('job', ['tjid', 'target_language']);
    $query->condition('job_item.item_id', $entity->id());
    $query->condition('job_item.item_type', $entity->getEntityTypeId());
    $query->condition('job.state', $state, '=');
    if ($poetry_state) {
      $query->condition('job.poetry_state', $poetry_state, '=');
    }
    else {
      $query->condition('job.poetry_state', NULL, 'IS NULL');
    }
    $query->condition('job.translator', 'poetry', '=');
    $result = $query->execute()->fetchAllAssoc('target_language');
    return $result ?? [];
  }

}
