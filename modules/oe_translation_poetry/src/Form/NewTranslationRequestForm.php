<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\oe_translation_poetry\NotificationEndpointResolver;
use Drupal\oe_translation_poetry\PoetryTranslatorUI;

/**
 * Form for requesting new translations.
 */
class NewTranslationRequestForm extends PoetryCheckoutFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_translation_poetry_new_translation_request';
  }

  /**
   * Returns the title of the form page.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $node
   *   The node entity to use when generating the title.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title for the form page.
   */
  public function getPageTitle(ContentEntityInterface $node = NULL): TranslatableMarkup {
    $queue = $this->queueFactory->get($node);
    $entity = $queue->getEntity();
    $target_languages = $queue->getTargetLanguages();
    $target_languages = implode(', ', $target_languages);
    return $this->t('Send request to DG Translation for <em>@entity</em> in <em>@target_languages</em>', ['@entity' => $entity->label(), '@target_languages' => $target_languages]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ContentEntityInterface $node = NULL) {
    $translator_settings = $this->poetry->getTranslatorSettings();
    $form = parent::buildForm($form, $form_state, $node);

    $default_contact = $translator_settings['contact'] ?? [];
    $form['details']['contact'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contact information'),
    ];

    foreach (PoetryTranslatorUI::getContactFieldNames('contact') as $name => $label) {
      $form['details']['contact'][$name] = [
        '#type' => 'textfield',
        '#title' => $label,
        '#default_value' => $default_contact[$name] ?? '',
        '#required' => TRUE,
      ];
    }
    $default_organisation = $translator_settings['organisation'] ?? [];
    $form['details']['organisation'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Organisation information'),
    ];

    foreach (PoetryTranslatorUI::getContactFieldNames('organisation') as $name => $label) {
      $form['details']['organisation'][$name] = [
        '#type' => 'textfield',
        '#title' => $label,
        '#default_value' => $default_organisation[$name] ?? '',
        '#required' => TRUE,
      ];
    }
    $form['details']['comment'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Comment'),
      '#description' => $this->t('Optional remark about the translation request.'),
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
    $entity = $form_state->get('entity');
    $queue = $this->queueFactory->get($entity);
    $translator_settings = $this->poetry->getTranslatorSettings();
    $jobs = $queue->getAllJobs();
    $identifier = $this->poetry->getIdentifierForContent($entity);
    $identifier->setProduct($this->requestType);

    $date = new \DateTime($form_state->getValue('details')['date']);
    $formatted_date = $date->format('d/m/Y');

    /** @var \EC\Poetry\Messages\Requests\CreateTranslationRequest $message */
    $message = $this->poetry->get('request.create_translation_request');
    $message->setIdentifier($identifier);

    // Build the details.
    $details = $message->withDetails();
    $details->setDelay($formatted_date);
    if ($form_state->getValue('details')['comment']) {
      $details->setRemark($form_state->getValue('details')['comment']);
    }
    $title = $this->createRequestTitle(reset($jobs));
    $details->setTitle($title);
    $details->setApplicationId($translator_settings['application_reference']);
    $details->setReferenceFilesRemark($entity->toUrl()->setAbsolute()->toString());
    $details
      ->setProcedure('NEANT')
      ->setDestination('PUBLIC')
      ->setType('INTER');

    // Add the organisation information.
    $organisation_information = [
      'setResponsible' => 'responsible',
      'setAuthor' => 'author',
      'setRequester' => 'requester',
    ];
    foreach ($organisation_information as $method => $name) {
      $details->$method($form_state->getValue('details')['organisation'][$name]);
    }

    $message->setDetails($details);

    // Build the contact information.
    foreach (PoetryTranslatorUI::getContactFieldNames('contact') as $name => $label) {
      $message->withContact()
        ->setType($name)
        ->setNickname($form_state->getValue('details')['contact'][$name]);
    }

    // Build the return endpoint information.
    $settings = $this->poetry->getSettings();
    $username = $settings['notification.username'] ?? NULL;
    $password = $settings['notification.password'] ?? NULL;
    $return = $message->withReturnAddress();
    $return->setUser($username);
    $return->setPassword($password);
    // The notification endpoint WSDL.
    $return->setAddress(NotificationEndpointResolver::resolve() . '?wsdl');
    // The notification endpoint WSDL action method.
    $return->setPath('handle');
    // The return is a webservice and not an email.
    $return->setType('webService');
    $return->setAction('UPDATE');
    $message->setReturnAddress($return);

    $source = $message->withSource();
    $source->setFormat('HTML');
    $source->setName('content.html');
    $formatted_content = $this->contentFormatter->export(reset($jobs));
    $source->setFile(base64_encode($formatted_content->__toString()));
    $source->setLegiswriteFormat('No');
    $source->withSourceLanguage()
      ->setCode(strtoupper($entity->language()->getId()))
      ->setPages(1);
    $message->setSource($source);

    foreach ($jobs as $job) {
      $message->withTarget()
        ->setLanguage(strtoupper($job->getRemoteTargetLanguage()))
        ->setFormat('HTML')
        ->setAction('INSERT')
        ->setDelay($formatted_date);
    }

    try {
      $client = $this->poetry->getClient();
      /** @var \EC\Poetry\Messages\Responses\ResponseInterface $response */
      $response = $client->send($message);
      $this->handlePoetryResponse($response, $form_state);

      // If we request a new number by setting a sequence, update the global
      // identifier number with the new number that came for future requests.
      if ($identifier->getSequence()) {
        $this->poetry->setGlobalIdentifierNumber($response->getIdentifier()->getNumber());
      }

      $this->redirectBack($form_state);
      $queue->reset();
      $this->messenger->addStatus($this->t('The request has been sent to DGT.'));
    }
    catch (\Exception $exception) {
      $this->logger->error($exception->getMessage());
      $this->messenger->addError($this->t('There was an error making the request to DGT.'));
      $this->redirectBack($form_state);
      $queue->reset();
    }
  }

}
