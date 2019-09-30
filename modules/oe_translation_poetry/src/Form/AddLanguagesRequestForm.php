<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Form for requesting new translations.
 */
class AddLanguagesRequestForm extends PoetryCheckoutFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_translation_poetry_add_languages_request';
  }

  /**
   * Returns the title of the form page.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title for the form page.
   */
  public function getPageTitle(): TranslatableMarkup {
    $target_languages = $this->queue->getTargetLanguages();
    $entity = $this->queue->getEntity();
    $target_languages = implode(', ', $target_languages);
    return $this->t('Add languages to previous request to DG Translation for <em>@entity</em>: <em>@target_languages</em>', ['@entity' => $entity->label(), '@target_languages' => $target_languages]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    $form['details'] = [
      '#type' => 'details',
      '#title' => $this->t('Request details'),
      '#open' => TRUE,
    ];

    $form['details']['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Requested delivery date'),
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send request'),
      '#button_type' => 'primary',
      '#submit' => ['::submitRequest'],
    ];

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel and delete job'),
      '#button_type' => 'secondary',
      '#submit' => ['::cancelRequest'],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * Submits the request to Poetry.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitRequest(array &$form, FormStateInterface $form_state): void {
    $translator_settings = $this->poetry->getTranslatorSettings();
    $jobs = $this->queue->getAllJobs();
    $entity = $this->queue->getEntity();
    $identifier = $this->poetry->getLastIdentifierForContent($entity);

    $date = new \DateTime($form_state->getValue('details')['date']);
    $formatted_date = $date->format('d/m/Y');

    /** @var \EC\Poetry\Messages\Requests\CreateTranslationRequest $message */
    $message = $this->poetry->get('request.add_languages_request');
    $message->setIdentifier($identifier);

    // Build the return endpoint information.
    $settings = $this->poetry->getSettings();
    $username = $settings['notification.username'] ?? NULL;
    $password = $settings['notification.password'] ?? NULL;
    $return = $message->withReturnAddress();
    $return->setUser($username);
    $return->setPassword($password);
    // The notification endpoint WSDL.
    $return->setAddress(Url::fromRoute('oe_translation_poetry.notifications')->setAbsolute()->toString() . '?wsdl');
    // The notification endpoint WSDL action method.
    $return->setPath('handle');
    // The return is a webservice and not an email.
    $return->setType('webService');
    $return->setAction($this->getRequestOperation());
    $message->setReturnAddress($return);

    foreach ($jobs as $job) {
      $message->withTarget()
        ->setLanguage(strtoupper($job->getTargetLangcode()))
        ->setFormat('HTML')
        ->setAction($this->getRequestOperation())
        ->setDelay($formatted_date);
    }

    try {
      $client = $this->poetry->getClient();
      /** @var \EC\Poetry\Messages\Responses\ResponseInterface $response */
      $response = $client->send($message);
      $this->handlePoetryResponse($response, $form_state);

      $this->redirectBack($form_state);
      $this->queue->reset();
      $this->messenger->addStatus($this->t('The request has been sent to DGT.'));
    }
    catch (\Exception $exception) {
      $this->logger->error($exception->getMessage());
      $this->messenger->addError($this->t('There was a error making the request to DGT.'));
      $this->redirectBack($form_state);
      $this->queue->reset();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequestOperation(): string {
    return 'INSERT';
  }

}
