<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation\Form\TranslationRequestForm;
use Drupal\oe_translation\LanguageWithStatus;
use Drupal\oe_translation\TranslationFormTrait;
use Drupal\oe_translation_remote\RemoteTranslationSynchroniser;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the reviewing remote translations for a given language.
 */
class RemoteTranslationReviewForm extends TranslationRequestForm {

  use TranslationFormTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The translation synchroniser.
   *
   * @var \Drupal\oe_translation_remote\RemoteTranslationSynchroniser
   */
  protected $translationSynchroniser;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $routeMatch, AccountInterface $currentUser, Messenger $messenger, RemoteTranslationSynchroniser $translationSynchroniser) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $routeMatch;
    $this->currentUser = $currentUser;
    $this->messenger = $messenger;
    $this->translationSynchroniser = $translationSynchroniser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('oe_translation_remote.translation_synchroniser')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $language = $this->routeMatch->getParameter('language');
    $entity = $this->getEntity();

    $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
    $field = $view_builder->viewField($entity->get('translated_data'), [
      'type' => 'oe_translation_remote_translation_data',
      'settings' => ['language' => $language],
    ]);

    $form['review'] = $field['translation'] ?? [];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\oe_translation_remote\TranslationRequestRemoteInterface $translation_request */
    $translation_request = $this->entity;
    $language = $this->routeMatch->getParameter('language');
    $form_state->set('language', $language);
    $language_status = $translation_request->getTargetLanguage($language->id());

    $actions = [];
    if (!$this->entity->isNew() && $this->entity->hasLinkTemplate('delete-form')) {
      $route_info = $this->entity->toUrl('delete-form');
      $query = $route_info->getOption('query');
      $entity = $translation_request->getContentEntity();
      $entity_type_id = $entity->getEntityTypeId();
      $query['destination'] = Url::fromRoute("entity.$entity_type_id.remote_translation", [$entity_type_id => $entity->id()])->toString();
      $route_info->setOption('query', $query);
      $actions['delete'] = [
        '#type' => 'link',
        '#weight' => 100,
        '#title' => $this->t('Delete'),
        '#access' => $this->entity->access('delete'),
        '#attributes' => [
          'class' => ['button', 'button--danger'],
        ],
      ];
      $actions['delete']['#url'] = $route_info;
    }

    if (!$language_status instanceof LanguageWithStatus) {
      return $actions;
    }

    $actions['save_and_accept'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#submit' => ['::saveData', '::accept'],
      '#access' => $language_status->getStatus() === TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW && $this->currentUser->hasPermission('accept translation request'),
      '#value' => $this->t('Save and accept'),
    ];

    $actions['save_and_sync'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#submit' => ['::saveData', '::synchronise'],
      '#access' => $language_status->getStatus() !== TranslationRequestRemoteInterface::STATUS_LANGUAGE_SYNCHRONISED && $this->currentUser->hasPermission('sync translation request'),
      '#value' => $this->t('Save and synchronise'),
    ];

    $actions['preview'] = [
      '#type' => 'submit',
      '#button_type' => 'secondary',
      '#submit' => ['::saveData', '::preview'],
      '#value' => $this->t('Preview'),
    ];

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // The regular submit does nothing.
  }

  /**
   * Saves the data in case the user updated it while reviewing.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function saveData(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\oe_translation_remote\TranslationRequestRemoteInterface $translation_request */
    $translation_request = $this->entity;
    $language = $form_state->get('language');

    $data = $translation_request->getTranslatedData();
    $data = $data[$language->getId()] ?? [];
    foreach ($form_state->getValues() as $key => $value) {
      if (is_array($value) && isset($value['translation'])) {
        // Update the translation, this will only update the translation in case
        // it has changed. We have two different cases, the first is for nested
        // texts.
        if (is_array($value['translation'])) {
          $update['#translation']['#text'] = $value['translation']['value'];
        }
        else {
          $update['#translation']['#text'] = $value['translation'];
        }
        $data = $this->updateData($key, $data, $update);
      }
    }

    $translation_request->setTranslatedData($language->getId(), $data);
    $translation_request->save();
  }

  /**
   * Accepts a translation request for a given language.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function accept(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\oe_translation_remote\TranslationRequestRemoteInterface $translation_request */
    $translation_request = $this->entity;
    $entity = $translation_request->getContentEntity();
    $language = $form_state->get('language');
    $translation_request->updateTargetLanguageStatus($language->id(), TranslationRequestRemoteInterface::STATUS_LANGUAGE_ACCEPTED);
    $translation_request->log('The <strong>@language</strong> translation has been accepted.', ['@language' => $language->getName()]);
    $translation_request->save();
    $this->messenger->addStatus($this->t('The translation in @language has been accepted.', ['@language' => $language->label()]));
    $form_state->setRedirect('entity.' . $entity->getEntityTypeId() . '.remote_translation', [$entity->getEntityTypeId() => $entity->id()]);
  }

  /**
   * Accepts a translation request for a given language.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function synchronise(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\oe_translation_remote\TranslationRequestRemoteInterface $translation_request */
    $translation_request = $this->entity;
    $entity = $translation_request->getContentEntity();
    $language = $form_state->get('language');
    $this->translationSynchroniser->synchronise($translation_request, $language->id());
    $form_state->setRedirect('entity.' . $entity->getEntityTypeId() . '.remote_translation', [$entity->getEntityTypeId() => $entity->id()]);
  }

  /**
   * Redirects the user to the preview path of the translation request.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function preview(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\oe_translation_local\TranslationRequestLocal $translation_request */
    $translation_request = $this->entity;

    // Clear the existing redirect if we have a destination.
    if ($this->getRequest()->query->get('destination')) {
      $this->getRequest()->query->remove('destination');
    }

    $language = $form_state->get('language');
    $url = $translation_request->toUrl('preview');
    $url->setRouteParameter('language', $language->id());
    $url->setOption('language', $language);
    $form_state->setRedirectUrl($url);
  }

  /**
   * Title callback for the translation request review form.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $oe_translation_request
   *   The translation request entity.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return array
   *   The title.
   */
  public function reviewTranslationFormTitle(TranslationRequestInterface $oe_translation_request, RouteMatchInterface $route_match): array {
    $entity = $oe_translation_request->getContentEntity();
    $language = $route_match->getParameter('language');
    return [
      '#markup' => $this->t('Review translation for @title in @language', [
        '@title' => $entity->label(),
        '@language' => $language ? $language->label() : '',
      ]),
    ];
  }

  /**
   * Access check for the remote translation review form.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $oe_translation_request
   *   The translation request.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The access current user.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The access route match.
   */
  public function access(TranslationRequestInterface $oe_translation_request, AccountInterface $account, RouteMatchInterface $route_match): AccessResultInterface {
    /** @var \Drupal\oe_translation_remote\TranslationRequestRemoteInterface $entity */
    $bundles = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getRemoteTranslationBundles();
    if (!in_array($oe_translation_request->bundle(), $bundles)) {
      // Only works for remote bundles.
      return AccessResult::forbidden();
    }

    $language = $route_match->getParameter('language');
    $data = $oe_translation_request->getTranslatedData();
    if (!isset($data[$language->id()])) {
      // If we have no data to translate, deny access.
      return AccessResult::forbidden();
    }

    $language_status = $oe_translation_request->getTargetLanguage($language->id());
    if ($language_status->getStatus() === TranslationRequestRemoteInterface::STATUS_LANGUAGE_SYNCHRONISED) {
      // If already synchronised, deny access.
      // @todo see if we should still keep this page accessible for other
      // reasons.
      return AccessResult::forbidden();
    }

    // We can allow access as it would be forbidden by another access checker
    // that checks for permissions.
    return AccessResult::allowed();
  }

}
