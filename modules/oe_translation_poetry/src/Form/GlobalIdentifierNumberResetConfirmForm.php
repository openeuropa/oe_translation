<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\oe_translation_poetry\Poetry;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for confirming we want to reset the identifier number.
 */
class GlobalIdentifierNumberResetConfirmForm extends ConfirmFormBase {

  /**
   * The poetry client.
   *
   * @var \Drupal\oe_translation_poetry\Poetry
   */
  protected $poetry;

  /**
   * Constructs the GlobalIdentifierNumberResetConfirmForm object.
   *
   * @param \Drupal\oe_translation_poetry\Poetry $poetry
   *   The poetry client.
   */
  public function __construct(Poetry $poetry) {
    $this->poetry = $poetry;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_translation_poetry.client.default')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Please confirm you want to reset the global identifier number on the next request made to DGT.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.tmgmt_translator.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return "poetry_reset_number_form";
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['actions']['cancel']['#url'] = Url::fromRoute('entity.tmgmt_translator.edit_form', ['tmgmt_translator' => 'poetry']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->poetry->forceNewIdentifierNumber(TRUE);
  }

}
