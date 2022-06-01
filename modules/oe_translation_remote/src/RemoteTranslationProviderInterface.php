<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote;

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
   * Creates or updates a translation request entity.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitRequest(FormStateInterface $form_state);

  /**
   * Sends the translation request entity to the translation provider.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitRequestToProvider(FormStateInterface $form_state);

}
