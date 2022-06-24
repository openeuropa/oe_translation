<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Remote translator provider plugin type form.
 */
class RemoteTranslatorProviderForm extends EntityForm {

  /**
   * The remote translation provider manager.
   *
   * @var \Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager
   */
  protected $remoteTranslationProviderManager;

  /**
   * Constructs a RemoteTranslatorProviderForm object.
   *
   * @param \Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager $remoteTranslationProviderManager
   *   The Remote translation provider manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(RemoteTranslationProviderManager $remoteTranslationProviderManager, MessengerInterface $messenger) {
    $this->remoteTranslationProviderManager = $remoteTranslationProviderManager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.oe_translation_remote.remote_translation_provider_manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\oe_translation_remote\Entity\RemoteTranslatorProvider $translator */
    $translator = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#maxlength' => 255,
      '#default_value' => $translator->label(),
      '#description' => $this->t('Name of the Remote translation provider.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $translator->id(),
      '#machine_name' => [
        'exists' => '\Drupal\oe_translation_remote\Entity\RemoteTranslatorProvider::load',
      ],
      '#disabled' => !$translator->isNew(),
    ];

    $definitions = $this->remoteTranslationProviderManager->getDefinitions();
    $options = [];
    foreach ($definitions as $id => $definition) {
      $options[$id] = $definition['label'];
    }

    $form['plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Plugin'),
      '#default_value' => $translator->getProviderPlugin(),
      '#options' => $options,
      '#description' => $this->t('The plugin to be used with this translator.'),
      '#required' => TRUE,
      '#empty_option' => $this->t('Please select a plugin'),
      '#ajax' => [
        'callback' => [$this, 'pluginConfigurationAjaxCallback'],
        'wrapper' => 'plugin-configuration-wrapper',
      ],
    ];

    $form['plugin_configuration'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'plugin-configuration-wrapper',
      ],
      '#tree' => TRUE,
      '#open' => TRUE,
    ];

    $plugin_id = NULL;
    if ($translator->getProviderPlugin()) {
      $plugin_id = $translator->getProviderPlugin();
    }
    if ($form_state->getValue('plugin') && $plugin_id !== $form_state->getValue('plugin')) {
      $plugin_id = $form_state->getValue('plugin');
    }

    if ($plugin_id) {
      $existing_config = $translator->getProviderConfiguration();
      $plugin = $this->remoteTranslationProviderManager->createInstance($plugin_id, $existing_config);

      $form['plugin_configuration']['#type'] = 'details';
      $form['plugin_configuration']['#title'] = $this->t('Plugin configuration for <em>@plugin</em>', ['@plugin' => $plugin->getPluginDefinition()['label']]);
      $form['plugin_configuration'][$plugin_id] = [
        '#process' => [[get_class($this), 'processPluginConfiguration']],
        '#plugin' => $plugin,
      ];
    }

    return $form;
  }

  /**
   * Ajax callback for the plugin configuration form elements.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function pluginConfigurationAjaxCallback(array $form, FormStateInterface $form_state) {
    return $form['plugin_configuration'];
  }

  /**
   * Process callback for the plugin configuration form.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The plugin configuration form.
   */
  public static function processPluginConfiguration(array &$element, FormStateInterface $form_state) {
    /** @var \Drupal\oe_translation_remote\RemoteTranslationProviderInterface $plugin */
    $plugin = $element['#plugin'];
    $subform_state = SubformState::createForSubform($element, $form_state->getCompleteForm(), $form_state);
    return $plugin->buildConfigurationForm($element, $subform_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    if ($form_state->getValue('plugin_configuration') == "") {
      $form_state->setValue('plugin_configuration', []);
    }

    /** @var \Drupal\oe_translation_remote\Entity\RemoteTranslatorProviderInterface $entity */
    $entity = parent::buildEntity($form, $form_state);

    $plugin_id = $form_state->getValue('plugin');
    if ($plugin_id) {
      $plugin_configuration = $form_state->getValue([
        'plugin_configuration',
        $plugin_id,
      ], []);
      $plugin = $this->remoteTranslationProviderManager->createInstance($plugin_id, $plugin_configuration);

      if (isset($form['plugin_configuration'][$plugin_id])) {
        $subform_state = SubformState::createForSubform($form['plugin_configuration'][$plugin_id], $form_state->getCompleteForm(), $form_state);
        $plugin->submitConfigurationForm($form['plugin_configuration'][$plugin_id], $subform_state);
      }

      $configuration = $plugin->getConfiguration();
      $entity->setProviderConfiguration($configuration);
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\oe_translation_remote\Entity\RemoteTranslatorProvider $translator */
    $translator = $this->entity;
    $status = $translator->save();
    switch ($status) {
      case SAVED_NEW:
        $this->messenger->addMessage($this->t('Created the %label Remote Translator Provider.', [
          '%label' => $translator->label(),
        ]));
        break;

      default:
        $this->messenger->addMessage($this->t('Saved the %label Remote Translator Provider.',
          [
            '%label' => $translator->label(),
          ]));
    }
    $form_state->setRedirectUrl($translator->toUrl('collection'));
  }

}
