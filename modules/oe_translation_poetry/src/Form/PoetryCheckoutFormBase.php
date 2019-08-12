<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\oe_translation_poetry\Poetry;
use Drupal\oe_translation_poetry\PoetryJobQueue;
use Drupal\oe_translation_poetry\PoetryTranslatorUI;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handles the checkout form for Poetry requests.
 */
abstract class PoetryCheckoutFormBase extends FormBase {

  /**
   * @var PoetryJobQueue
   */
  protected $queue;

  /**
   * @var \Drupal\oe_translation_poetry\Poetry
   */
  protected $poetry;

  /**
   * PoetryCheckoutForm constructor.
   *
   * @param \Drupal\oe_translation_poetry\PoetryJobQueue $queue
   * @param \Drupal\oe_translation_poetry\Poetry $poetry
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   */
  public function __construct(PoetryJobQueue $queue, Poetry $poetry, MessengerInterface $messenger) {
    $this->queue = $queue;
    $this->poetry = $poetry;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_translation_poetry.job_queue'),
      $container->get('oe_translation_poetry.client'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // @todo create access control checker that denies access if there are no jobs in the queue.

    $form['details'] = [
      '#type' => 'details',
      '#title' => $this->t('Request details'),
      // @todo determine if we can have a sensible default.
      '#open' => TRUE,
    ];

    $form['details']['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Requested delivery date'),
      '#required' => TRUE,
    ];

    $form['details']['contact'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contact information'),
    ];

    foreach (PoetryTranslatorUI::getContactFieldNames('contact') as $name => $label) {
      $form['details']['contact'][$name] = [
        '#type' => 'textfield',
        '#title' => $label,
      ];
    }

    $form['details']['organisation'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Organisation information'),
    ];

    foreach (PoetryTranslatorUI::getContactFieldNames('organisation') as $name => $label) {
      $form['details']['organisation'][$name] = [
        '#type' => 'textfield',
        '#title' => $label,
      ];
    }

    $form['details']['comment'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Comment'),
      '#description' => $this->t('Optional remark about the translation request.'),
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
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // The submit handler is submitRequest().
  }

  /**
   * The type of request form: CREATE, UPDATE, DELETE.
   *
   * @return mixed
   */
  abstract protected function getRequestType();

  /**
   * Submits the request to poetry.
   *
   * @todo refactor to allow subclasses to handle different types of requests.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @throws \Exception
   */
  public function submitRequest(array &$form, FormStateInterface $form_state): void {
    $jobs = $this->queue->getAllJobs();
    $entity = $this->queue->getEntity();
    $identifier = $this->poetry->getIdentifierForContent($entity);
    $identifier->setProduct('TRA');

    $date = new \DateTime($form_state->getValue('date'));
    $formatted_date = $date->format('d/m/Y');

    /** @var \EC\Poetry\Messages\Requests\CreateTranslationRequest $message */
    $message = $this->poetry->get('request.create_translation_request');
    $message->setIdentifier($identifier);

    // Build the details.
    $details = $message->withDetails();
    $details->setDelay($formatted_date);

    if ($form_state->getValue('comment')) {
      $details->setRemark($form_state->getValue('comment'));
    }

    // We use the formatted identifier as the user reference.
    $details->setClientId($identifier->getFormattedIdentifier());
    // @todo load the prefix and site name from configuration.
    $title = 'EWCMS: SITE-NAME . ' . reset($jobs)->label();
    $details->setTitle($title);
    //@ todo load the application ID from config.
    $application_id = 'FPFIS';
    $details->setApplicationId($application_id);
    $details->setReferenceFilesRemark($entity->toUrl()->setAbsolute()->toString());
    $details
      ->setProcedure('NEANT')
      ->setDestination('PUBLIC')
      ->setType('INTER');

    $message->setDetails($details);

    // Build the contact information.
    foreach (PoetryTranslatorUI::getContactFieldNames('contact') as $name => $label) {
      $message->withContact()
        ->setType($name)
        ->setNickname($form_state->getValue($name));
    }

    // Build the organisation information.
    foreach (PoetryTranslatorUI::getContactFieldNames('organisation') as $name => $label) {
      $message->withContact()
        ->setType($name)
        ->setNickname($form_state->getValue($name));
    }

    // Build the return endpoint information.
    // @todo update once we implemented the notification handling.
    $return = $message->getReturnAddress();
    $return->setUser('test');
    $return->setPassword('test');
    $return->setAddress(Url::fromRoute('<front>')->setAbsolute()->toString());
    $return->setPath('handle');
    $message->setReturnAddress($return);

    // @todo for the source, OPENEUROPA-2156
    $source = $message->withSource();
    $source->setFormat('HTML');
    $source->setName('content.html');
    $source->setFile(base64_encode('test value'));
    $source->setLegiswriteFormat('No');
    $source->withSourceLanguage()
      ->setCode($entity->language()->getId())
      ->setPages(1);
    $message->setSource($source);

    foreach ($jobs as $job) {
      $message->withTarget()
        ->setLanguage($job->getTargetLangcode())
        ->setFormat('HTML')
        // @todo change to UPDATE if it's an update of the same request.
        ->setAction($this->getRequestType())
        ->setDelay($formatted_date);
    }

    $client = $this->poetry->getClient();
    $client->send($message);
  }

  /**
   * Cancels the request and deletes the jobs that had been created.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @throws \Exception
   */
  public function cancelRequest(array &$form, FormStateInterface $form_state): void {
    $jobs = $this->queue->getAllJobs();
    foreach ($jobs as $job) {
      $job->delete();
    }

    $destination = $this->queue->getDestination();
    if ($destination) {
      $form_state->setRedirectUrl($destination);
    }

    $this->queue->reset();
    $this->messenger->addStatus($this->t('The translation request has been cancelled and the corresponding jobs deleted.'));
  }

}
