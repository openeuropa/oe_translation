<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_translation\TranslationSourceManagerInterface;
use Drupal\oe_translation_remote\RemoteTranslationProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for remote_translation_provider plugins.
 */
abstract class RemoteTranslationProviderBase extends PluginBase implements RemoteTranslationProviderInterface, ConfigurableInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The entity being translated.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The translation source manager.
   *
   * @var \Drupal\oe_translation\TranslationSourceManagerInterface
   */
  protected $translationSourceManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $languageManager, EntityTypeManagerInterface $entityTypeManager, TranslationSourceManagerInterface $translationSourceManager, MessengerInterface $messenger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->setConfiguration($configuration);
    $this->languageManager = $languageManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->translationSourceManager = $translationSourceManager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('oe_translation.translation_source_manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['no_configuration'] = [
      '#markup' => $this->t('This plugin does not have any configuration options.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Do nothing by default.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Do nothing by default.
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntity(ContentEntityInterface $entity): void {
    $this->entity = $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function newTranslationRequestForm(array &$form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitRequestToProvider(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function validateRequest(array &$form, FormStateInterface $form_state): void {}

}
