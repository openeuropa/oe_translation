<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Controller;

use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\tmgmt\TMGMTException;
use Drupal\tmgmt\TranslatorManager;
use Drupal\tmgmt_local\LocalTaskInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller responsible for routes that deal with the translation local tasks.
 */
class LocalTasksController extends ControllerBase {

  /**
   * The translator manager.
   *
   * @var \Drupal\tmgmt\TranslatorManager
   */
  protected $translatorManager;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Initializes the content translation controller.
   *
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $manager
   *   A content translation manager instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\tmgmt\TranslatorManager $translator_manager
   *   The translation manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(ContentTranslationManagerInterface $manager, EntityTypeManagerInterface $entity_type_manager, TranslatorManager $translator_manager, AccountProxyInterface $current_user, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->translatorManager = $translator_manager;
    $this->currentUser = $current_user;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('content_translation.manager'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.tmgmt.translator'),
      $container->get('current_user'),
      $container->get('request_stack')
    );
  }

  /**
   * Creates and assigns a translation job to the current user.
   *
   * This is meant to be used with the PermissionTranslator plugin which allows
   * the auto-assignment of local tasks to the current user if they have a
   * certain permission.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to translate.
   * @param \Drupal\Core\Language\Language $source
   *   The source language.
   * @param \Drupal\Core\Language\Language $target
   *   The target language of the translation.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the translation page on success or to the translation
   *   overview on failure.
   */
  public function createLocalTranslationTask(EntityInterface $entity, Language $source, Language $target): RedirectResponse {
    /** @var \Drupal\tmgmt\JobInterface $job */
    $job = $this->entityTypeManager->getStorage('tmgmt_job')->create([
      'translator' => 'permission',
      'source_language' => $source->getId(),
      'target_language' => $target->getId(),
      'uid' => $this->currentUser->id(),
    ]);

    try {
      // Add the job item.
      $item = $job->addItem('content', $entity->getEntityTypeId(), $entity->id());

      // Create local task for this job.
      /** @var \Drupal\tmgmt_local\LocalTaskInterface $local_task */
      $local_task = $this->entityTypeManager->getStorage('tmgmt_local_task')->create([
        'uid' => $job->getOwnerId(),
        'tuid' => $this->currentUser->id(),
        'tjid' => $job->id(),
        'title' => $job->label(),
        'status' => LocalTaskInterface::STATUS_PENDING,
      ]);
      $local_task->save();

      /** @var \Drupal\tmgmt_local\LocalTaskItemInterface $local_item */
      $local_item = $local_task->addTaskItem($item);
      $item->active();
      $job->submitted();

      $url = $local_item->toUrl();
      $url->setOption('language', $target);

      // Pass on any destinations to the actually intended form page.
      if ($destination = $this->request->query->get('destination')) {
        $this->request->query->remove('destination');
        $url->setOption('query', ['destination' => $destination]);
      }
      return new RedirectResponse($url->toString());
    }

    catch (TMGMTException $e) {
      watchdog_exception('tmgmt', $e);
      $this->messenger->addError(t('Unable to add job item for target language %name. Make sure the source content is not empty.', [
        '%name' => $target->getName(),
      ]));

      return new RedirectResponse($entity->toUrl('drupal:content-translation-overview')->toString());
    }
  }

  /**
   * Access callback to create a local task item using the PermissionTranslator.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to translate.
   * @param \Drupal\Core\Language\Language $source
   *   The source language.
   * @param \Drupal\Core\Language\Language $target
   *   The target language of the translation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function createLocalTranslationTaskAccess(EntityInterface $entity, Language $source, Language $target, AccountInterface $account): AccessResultInterface {
    $definition = $this->translatorManager->getDefinition('permission');
    $permission = $definition['default_settings']['permission'];
    $result = AccessResult::allowedIfHasPermission($account, $permission);

    // Check that a job item doesn't already exist for this translation.
    $job_items = tmgmt_job_item_load_latest('content', $entity->getEntityTypeId(), $entity->id(), $source->getId());
    $result->andIf(AccessResult::allowedIf(!isset($job_items[$target->getId()])));

    $job_item_definition = $this->entityTypeManager->getDefinition('tmgmt_job_item');
    $result->addCacheTags($job_item_definition->getListCacheTags());

    return $result;
  }

}
