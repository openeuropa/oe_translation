<?php

declare(strict_types=1);

namespace Drupal\oe_translation_local\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Single target language' formatter.
 *
 * Shows the translation request target language for local translations.
 *
 * @FieldFormatter(
 *   id = "oe_translation_local_single_target_language",
 *   label = @Translation("Single target language"),
 *   field_types = {
 *     "oe_translation_language_with_status"
 *   }
 * )
 */
class SingleTargetLanguage extends FormatterBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'data_value' => 'language',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements['data_value'] = [
      '#title' => $this->t('What data value should it show?'),
      '#type' => 'select',
      '#options' => [
        'language' => $this->t('Language'),
        'status' => $this->t('Status'),
      ],
      '#default_value' => $this->getSetting('data_value'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Showing @data_value', ['@data_value' => $this->getSetting('data_value')]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      $value = '';
      if ($this->getSetting('data_value') === 'language') {
        $language = $this->entityTypeManager->getStorage('configurable_language')->load($item->langcode);
        $value = $language->label();
      }
      if ($this->getSetting('data_value') === 'status') {
        $value = $item->status;
      }

      $elements[$delta] = [
        '#type' => 'markup',
        '#markup' => $value,
      ];
    }

    return $elements;
  }

}
