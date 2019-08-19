<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry;

use Drupal\Core\Form\FormStateInterface;
use Drupal\tmgmt\TranslatorPluginUiBase;

/**
 * UI class for the Poetry translator.
 */
class PoetryTranslatorUI extends TranslatorPluginUiBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $form_state->getFormObject()->getEntity();
    $form['service_wsdl'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Poetry Service URL'),
      '#default_value' => $translator->getSetting('service_wsdl'),
      '#description' => $this->t('The url to the Poetry service WSDL.'),
    ];
    $form['identifier_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Identifier code'),
      '#default_value' => $translator->getSetting('identifier_code'),
      '#description' => $this->t('The default identifier code for any request. It is WEB by default and should only be changed in very specific scenarios.'),
    ];
    $form['title_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Request title prefix'),
      '#default_value' => $translator->getSetting('title_prefix'),
      '#description' => $this->t('This string will be prefixed to the title of every request sent. It should help identify the origin of the request.'),
    ];
    $form['site_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site ID'),
      '#default_value' => $translator->getSetting('site_id'),
      '#description' => $this->t('This site ID used in the title of translation requests. Defaults to the Drupal site name.'),
    ];
    $form['application_reference'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application reference code'),
      '#default_value' => $translator->getSetting('application_reference'),
      '#description' => $this->t('The application reference code identifies which type of application sent the request.'),
    ];
    $form['contact'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contact information'),
    ];
    $contact_defaults = $translator->getSetting('contact');
    foreach (static::getContactFieldNames('contact') as $name => $label) {
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
    foreach (static::getContactFieldNames('organisation') as $name => $label) {
      $form['organisation'][$name] = [
        '#type' => 'textfield',
        '#title' => $label,
        '#default_value' => $organisation_defaults[$name],
      ];
    }

    return $form;
  }

  /**
   * Returns the field names for a given type of contact fieldset.
   *
   * @param string $type
   *   The field type.
   *
   * @return array
   *   Array with the machine names and readable names of the fields.
   */
  public static function getContactFieldNames(string $type = 'contact'): array {
    $map = [
      'contact' => [
        'author' => t('Author'),
        'secretary' => t('Secretary'),
        'contact' => t('Contact'),
        'responsible' => t('Responsible'),
      ],
      'organisation' => [
        'responsible' => t('Responsible'),
        'author' => t('Author'),
        'requester' => t('Requester'),
      ],
    ];

    return $map[$type] ?? [];
  }

}
