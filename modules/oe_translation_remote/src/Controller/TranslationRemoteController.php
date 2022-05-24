<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for the Remote translation task.
 */
class TranslationRemoteController extends ControllerBase {

  /**
   * Renders the remote translation overview page.
   */
  public function overview(): array {
    return [
      '#markup' => $this->t('The remote translations overview page.'),
    ];
  }

}
