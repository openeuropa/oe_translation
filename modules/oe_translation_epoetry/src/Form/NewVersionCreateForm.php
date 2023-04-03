<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_translation_epoetry\EpoetryOngoingNewVersionRequestHandlerInterface;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager;
use Drupal\oe_translation_remote\RemoteTranslationProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Form for new version requests for ongoing translations.
 */
class NewVersionCreateForm extends FormBase {

  /**
   * The remote translation provider manager.
   *
   * @var \Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager
   */
  protected $providerManager;

  /**
   * The new version request handler.
   *
   * @var \Drupal\oe_translation_epoetry\EpoetryOngoingNewVersionRequestHandlerInterface
   */
  protected $newVersionRequestHandler;

  /**
   * Constructs a NewVersionCreateForm.
   *
   * @param \Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager $providerManager
   *   The remote translation provider manager.
   * @param \Drupal\oe_translation_epoetry\EpoetryOngoingNewVersionRequestHandlerInterface $newVersionRequestHandler
   *   The new version request handler.
   */
  public function __construct(RemoteTranslationProviderManager $providerManager, EpoetryOngoingNewVersionRequestHandlerInterface $newVersionRequestHandler) {
    $this->providerManager = $providerManager;
    $this->newVersionRequestHandler = $newVersionRequestHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.oe_translation_remote.remote_translation_provider_manager'),
      $container->get('oe_translation_epoetry.new_version_request_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_translation_epoetry_create_new_version_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, TranslationRequestEpoetryInterface $translation_request = NULL) {
    if (!$translation_request) {
      throw new NotFoundHttpException();
    }

    $form['#tree'] = TRUE;

    $plugin = $this->instantiatePlugin($translation_request);
    // Mark the form state that we are creating a new version from an ongoing
    // state. This is so that the plugin knows to mark the old one as finished
    // and reference it in the log of the new one.
    $form_state->set('create_new_version_ongoing', TRUE);
    $form_state->set('epoetry_last_request', $translation_request);
    $form_state->set('translator_id', $translation_request->getTranslatorProvider()->id());
    $form['#parents'] = [];
    $form = $plugin->newTranslationRequestForm($form, $form_state);
    $form_state->set('plugin', $plugin);

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and send'),
      '#submit' => ['::send'],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => ['::cancel'],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\oe_translation_remote\RemoteTranslationProviderInterface $plugin */
    $plugin = $form_state->get('plugin');
    $plugin->validateRequest($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function send(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\oe_translation_remote\RemoteTranslationProviderInterface $plugin */
    $plugin = $form_state->get('plugin');
    $plugin->submitRequestToProvider($form, $form_state);
  }

  /**
   * Submit handler to cancel and go back.
   */
  public function cancel(array &$form, FormStateInterface $form_state) {
    // We don't have to do anything as the form system will automatically
    // redirect us back to where we came from.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // We do nothing here as we have custom submit buttons.
  }

  /**
   * Creates the ePoetry plugin from the translation request.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $translation_request
   *   The translation request.
   *
   * @return \Drupal\oe_translation_remote\RemoteTranslationProviderInterface
   *   The plugin.
   */
  protected function instantiatePlugin(TranslationRequestEpoetryInterface $translation_request): RemoteTranslationProviderInterface {
    $translator = $translation_request->getTranslatorProvider();
    $plugin = $this->providerManager->createInstance($translator->getProviderPlugin(), $translator->getProviderConfiguration());
    $entity = $this->newVersionRequestHandler->getUpdateEntity($translation_request);
    $plugin->setEntity($entity);

    return $plugin;
  }

}
