<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Plugin\Field\FieldWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;

/**
 * Defines the 'Translation Synchronisation Widget' field widget.
 *
 * @FieldWidget(
 *   id = "oe_translation_translation_sync_widget",
 *   label = @Translation("Translation Synchronisation Widget"),
 *   field_types = {"oe_translation_translation_sync"},
 * )
 */
class TranslationSynchronisationWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element += [
      '#type' => 'fieldset',
    ];

    $element['type'] = [
      '#type' => 'select',
      '#description' => $this->t('Select the type of synchronization rule.'),
      '#title' => $this->t('Select type'),
      '#options' => static::getSyncTypeOptions(),
      '#default_value' => isset($items[$delta]->type) ? $items[$delta]->type : NULL,
    ];

    // Get the selector to build the paths for type field.
    $parents = $element['#field_parents'];
    $parents[] = $this->fieldDefinition->getName();
    $parents[] = $element['#delta'];
    $selector = array_shift($parents);
    if ($parents) {
      $selector .= '[' . implode('][', $parents) . ']';
    }

    $element['configuration'] = [
      '#type' => 'container',
      '#title' => $this->t('Configuration'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          'select[name="' . $selector . '[type]"]' => ['value' => 'automatic'],
        ],
      ],
    ];

    $element['configuration']['languages'] = [
      '#type' => 'language_select',
      '#multiple' => TRUE,
      '#title' => $this->t('Language'),
      '#description' => $this->t('Select the languages that need to have been approved before they can be synchronized.'),
      '#default_value' => isset($items[$delta]->configuration['language']) ? $items[$delta]->configuration['language'] : NULL,
      '#languages' => LanguageInterface::STATE_CONFIGURABLE,
      '#size' => 10,
      '#states' => [
        'required' => [
          'select[name="' . $selector . '[type]"]' => ['value' => 'automatic'],
        ],
      ],
    ];

    $element['configuration']['date'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Date'),
      '#description' => $this->t('The date by which to synchronize all approved translations regardless of which languages have been approved yet.'),
      '#default_value' => isset($items[$delta]->configuration['date']) ? DrupalDateTime::createFromTimestamp($items[$delta]->configuration['date']) : NULL,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $values = parent::massageFormValues($values, $form, $form_state);
    foreach ($values as $delta => &$item_values) {
      if ($item_values['type'] === 'manual') {
        $item_values['configuration'] = [];
        continue;
      }

      if ($item_values['configuration']['date'] instanceof DrupalDateTime) {
        $item_values['configuration']['date'] = $item_values['configuration']['date']->getTimestamp();
      }
    }

    return $values;
  }

  /**
   * Returns the options for type.
   *
   * @return array
   *   List of options.
   */
  public static function getSyncTypeOptions(): array {
    return [
      'manual' => t('Manual'),
      'automatic' => t('Automatic with minimum threshold'),
    ];
  }

}
