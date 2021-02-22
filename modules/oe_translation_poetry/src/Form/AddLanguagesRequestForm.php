<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\oe_translation_poetry\NotificationEndpointResolver;

/**
 * Form for requesting the addition of new languages to an existing request.
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
    return $this->t('Send extra languages to the previous request for <em>@entity</em>: <em>@target_languages</em>', ['@entity' => $entity->label(), '@target_languages' => $target_languages]);
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
    $jobs = $queue->getAllJobs();
    // We use the last identifier of the content and we don't increment the
    // version in this case.
    $identifier = $this->poetry->getLastIdentifierForContent($entity);
    $identifier->setProduct($this->requestType);

    $date = new \DateTime($form_state->getValue('details')['date']);
    $formatted_date = $date->format('d/m/Y');

    /** @var \EC\Poetry\Messages\Requests\AddLanguagesRequest $message */
    $message = $this->poetry->get('request.add_languages_request');
    $message->setIdentifier($identifier);

    // Build the return endpoint information.
    $settings = $this->poetry->getSettings();
    $username = $settings['notification.username'] ?? NULL;

    foreach ($jobs as $job) {
      $message->withTarget()
        ->setLanguage(strtoupper($job->getRemoteTargetLanguage()))
        ->setFormat('HTML')
        ->setDelay($formatted_date)
        ->withReturnAddress()
        ->setType('smtp')
        ->setUser($username)
        ->setAddress(NotificationEndpointResolver::resolve() . '?wsdl');
    }

    try {
      $client = $this->poetry->getClient();
      /** @var \EC\Poetry\Messages\Responses\ResponseInterface $response */
      $response = $client->send($message);
      $this->handlePoetryResponse($response, $form_state);

      $this->redirectBack($form_state);
      $queue->reset();
      $this->messenger->addStatus($this->t('The request has been sent to DGT.'));
    }
    catch (\Exception $exception) {
      $this->logger->error($exception->getMessage());
      $this->messenger->addError($this->t('There was a error making the request to DGT.'));
      $this->redirectBack($form_state);
      $queue->reset();
    }
  }

}
