<?php

namespace Drupal\oe_translation;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Interface for TMGMT translator plugins that can alter translation pages.
 */
interface AlterableTranslatorInterface {

  /**
   * Alters the job item form.
   *
   * This is the form used for translating locally content.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function jobItemFormAlter(array &$form, FormStateInterface $form_state): void;

  /**
   * Alters the content translation overview page.
   *
   * @param array $build
   *   The overview page.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param string|null $entity_type_id
   *   The entity type ID being translated.
   */
  public function contentTranslationOverviewAlter(array &$build, RouteMatchInterface $route_match, $entity_type_id): void;

}
