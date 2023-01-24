<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface for remote_translation_provider plugins.
 */
interface RemoteTranslationProviderInterface extends PluginFormInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label(): string;

  /**
   * Gets the translated entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function getEntity(): ContentEntityInterface;

  /**
   * Sets the translated entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function setEntity(ContentEntityInterface $entity): void;

  /**
   * Prepares the form for creating a new translation request form.
   *
   * @param array $form
   *   The subform.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form elements.
   */
  public function newTranslationRequestForm(array &$form, FormStateInterface $form_state): array;

  /**
   * Sends the translation request entity to the translation provider.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitRequestToProvider(array &$form, FormStateInterface $form_state): void;

  /**
   * Validates the translation request before sending translation provider.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateRequest(array &$form, FormStateInterface $form_state): void;

}
