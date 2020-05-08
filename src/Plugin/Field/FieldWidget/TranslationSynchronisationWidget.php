<?php

namespace Drupal\oe_translation\Plugin\Field\FieldWidget;

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
      '#title' => t('Select type'),
      '#options' => $this->getTypeOptions(),
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
      '#type' => 'details',
      '#title' => 'Configuration',
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="' . $selector . '[type]"]' => ['value' => 'automatic'],
        ],
      ],
    ];
    $element['configuration']['language'] = [
      '#type' => 'language_select',
      '#title' => t('Select language'),
      '#default_value' => isset($items[$delta]->configuration['language']) ? $items[$delta]->configuration['language'] : NULL,
      '#languages' => LanguageInterface::STATE_CONFIGURABLE,
      '#required' => TRUE,
    ];
    $element['configuration']['date'] = [
      '#type' => 'date',
      '#title' => t('Date'),
      '#default_value' => isset($items[$delta]->configuration['date']) ? $items[$delta]->configuration['date'] : '',
    ];

    return $element;
  }

  /**
   * Returns the options for type.
   *
   * @return array
   *   List of options.
   */
  protected function getTypeOptions() {
    return [
      'manual' => 'Manual',
      'automatic' => 'Automatic with minimum threshold',
    ];
  }

}
