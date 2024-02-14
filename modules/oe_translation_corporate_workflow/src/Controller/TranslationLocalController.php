<?php

declare(strict_types=1);

namespace Drupal\oe_translation_corporate_workflow\Controller;

use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation_corporate_workflow\CorporateWorkflowTranslationTrait;
use Drupal\oe_translation_local\Controller\TranslationLocalController as OriginalTranslationLocalController;

/**
 * Controller for altering things on the local translation system.
 */
class TranslationLocalController extends OriginalTranslationLocalController {

  use CorporateWorkflowTranslationTrait;

  /**
   * Title callback for the translation request form to translate locally.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $oe_translation_request
   *   The translation request entity.
   *
   * @return array
   *   The title.
   */
  public function translateLocalFormTitle(TranslationRequestInterface $oe_translation_request): array {
    $entity = $oe_translation_request->getContentEntity();
    $version = $this->getEntityVersion($entity);
    if (!$version) {
      return parent::translateLocalFormTitle($oe_translation_request);
    }

    return [
      '#markup' => $this->t('Translate @title in @language (version @version)', [
        '@title' => $entity->label(),
        '@language' => $this->getTargetLanguageFromRequest($oe_translation_request)->getName(),
        '@version' => $version,
      ]),
    ];

  }

}
