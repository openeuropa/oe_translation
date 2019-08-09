<?php

declare(strict_types = 1);
namespace Drupal\oe_translation_poetry;

use Drupal\Core\Form\FormStateInterface;
use Drupal\tmgmt\TranslatorPluginUiBase;

/**
 * Form Provider for the Poetry Translator.
 */
class PoetryTranslatorUI extends TranslatorPluginUiBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $form_state->getFormObject()->getEntity();
    $form['identifier'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Identifier code'),
      '#default_value' => $translator->getSetting('identifier'),
      '#description' => t('The default identifier code for any request. It should be WEB by default and should only be changed in very specific scenarios.'),
    ];
    $form['title_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Request title prefix'),
      '#default_value' => $translator->getSetting('title_prefix'),
      '#description' => t('This string will be prefixed to the title of every request sent. It should help identify the origin of the request.'),
    ];
    $form['application_reference'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application reference code'),
      '#default_value' => $translator->getSetting('application_reference'),
      '#description' => t('The application reference code identifies which type of application sent the request.'),
    ];
    $form['contact'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contact information'),
    ];
    $contact_defaults = $translator->getSetting('contact');
    foreach ($this->getContactFieldNames('contact') as $name => $label) {
      $form['contact'][$name] = [
        '#type' => 'textfield',
        '#title' => $label,
        '#default_value' => $contact_defaults[$name],
      ];
    }
    $form['organisation'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Organisation information'),
    ];
    $organisation_defaults = $translator->getSetting('organisation');
    foreach ($this->getContactFieldNames('organisation') as $name => $label) {
      $form['organisation'][$name] = [
        '#type' => 'textfield',
        '#title' => $label,
        '#default_value' => $organisation_defaults[$name],
      ];
    }

    return $form;
  }

  /**
   * Returns the field names for a given type of contact field set.
   *
   * @param string $type
   *   The field type.
   *
   * @return array
   *   Array with the machine names and readable names of the fields.
   */
  protected function getContactFieldNames(string $type = 'contact'): array {
    $map = [
      'contact' => [
        'author' => $this->t('Author'),
        'secretary' => $this->t('Secretary'),
        'contact' => $this->t('Contact'),
        'responsible' => $this->t('Responsible'),
      ],
      'organisation' => [
        'responsible' => $this->t('Responsible'),
        'author' => $this->t('Author'),
        'requester' => $this->t('Requester'),
      ],
    ];
    return $map[$type] ?? [];

  }

}
