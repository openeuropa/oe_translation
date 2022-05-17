<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\oe_translation_poetry\Plugin\Field\FieldType\PoetryRequestIdItem;
use Drupal\oe_translation_poetry\Plugin\tmgmt\Translator\PoetryTranslator;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobInterface;
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

    // Add the option to reset the global identifier number.
    /** @var \Drupal\oe_translation_poetry\Poetry $poetry_client */
    $poetry_client = \Drupal::service('oe_translation_poetry.client.default');
    $form['number_reset'] = [
      '#type' => 'details',
      '#title' => $this->t('Poetry number reset'),
      '#open' => TRUE,
    ];

    if ($poetry_client->isNewIdentifierNumberRequired()) {
      $reset = [
        '#type' => 'inline_template',
        '#template' => '<strong>{{ message }}</strong>',
        '#context' => [
          'message' => $this->t('The number will be reset with the first request made to Poetry.'),
        ],
      ];
    }
    else {
      $reset = [
        '#type' => 'link',
        '#url' => Url::fromRoute('oe_translation_poetry.confirm_number_reset', [], [
          'attributes' => ['class' => ['button']],
          'query' => [
            'destination' => Url::fromRoute('<current>')->toString(),
          ],
        ]),
        '#title' => $this->t('Reset number'),
      ];
    }

    $form['number_reset']['info'] = [
      '#type' => 'inline_template',
      '#template' => '<p>{{ message }}</p><p>{{ reset }}</p>',
      '#context' => [
        'message' => $this->t('The Poetry number is given by Poetry whenever requests are sent using the SEQUENCE (or COUNTER). For example, <strong>1000</strong> in the following example request ID: WEB/2020/<strong>1000</strong>/0/10/TRA.
                               Reset this number only if requests made to Poetry are blocked due to duplicated IDs which prevent the sending of new requests. Normally this should not happen.'),
        'reset' => $reset,
      ],
    ];

    $form['contact'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contact information'),
    ];

    $contact_defaults = $translator->getSetting('contact');
    foreach (static::getContactFieldNames() as $name => $label) {
      $form['contact'][$name] = [
        '#type' => 'textfield',
        '#title' => $label,
        '#default_value' => $contact_defaults[$name] ?? NULL,
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
        '#default_value' => $organisation_defaults[$name] ?? NULL,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function checkoutInfo(JobInterface $job) {
    $build = [];

    $request_id = $job->get('poetry_request_id')->first()->getValue();
    $build[] = [
      '#markup' => $this->t('Request reference: @ref', ['@ref' => PoetryRequestIdItem::toReference($request_id)]) . '<br />',
    ];

    $poetry_state = $job->get('poetry_state')->value;

    if ($poetry_state === PoetryTranslator::POETRY_STATUS_ONGOING) {
      $build[] = [
        '#markup' => $this->t('This job is being translated in Poetry.'),
      ];
    }

    if ($poetry_state === PoetryTranslator::POETRY_STATUS_TRANSLATED) {
      $build[] = [
        '#markup' => $this->t('This job has been translated by Poetry and needs to be reviewed.'),
      ];
    }

    if ((int) $job->getState() === Job::STATE_UNPROCESSED) {
      $build[] = [
        '#markup' => $this->t('This job is not yet processed, meaning it has not been yet sent to Poetry.'),
      ];
    }

    if ((int) $job->getState() === Job::STATE_ACTIVE) {
      $build[] = [
        '#markup' => $this->t('This job has been submitted to Poetry but no response has come yet.'),
      ];
    }

    return $build;
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
        'auteur' => t('Author'),
        'secretaire' => t('Secretary'),
        'contact' => t('Contact'),
        'responsable' => t('Responsible'),
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
