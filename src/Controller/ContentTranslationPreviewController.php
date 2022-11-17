<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Controller;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\tmgmt_content\Controller\ContentTranslationPreviewController as TmgmtContentTranslationPreviewController;

/**
 * Content preview translation controller.
 *
 * We extend from TMGMT to reuse some of its logic that applies to us as well.
 */
class ContentTranslationPreviewController extends TmgmtContentTranslationPreviewController {

  /**
   * Preview the translation request entity underlying translation data.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $oe_translation_request
   *   The current translation request.
   * @param string $language
   *   The language to preview in.
   *
   * @return array
   *   A render of the translated entity.
   */
  public function previewRequest(TranslationRequestInterface $oe_translation_request, string $language): array {
    // Get the entity being translated.
    $entity = $oe_translation_request->getContentEntity();
    if (!$entity) {
      throw new \Exception('There is no entity currently being translated.');
    }

    // Retrieve the translation data.
    $data = $oe_translation_request->getData();

    // Generate the translation preview for the provided data.
    $translation = $this->getTranslationPreview($entity, $data, $language);
    $translation->in_preview = TRUE;

    // Build view for entity.
    $preview = $this->entityTypeManager
      ->getViewBuilder($entity->getEntityTypeId())
      ->view($translation, 'full', $translation->language()->getId());

    // The preview is not cacheable.
    $preview['#cache']['max-age'] = 0;
    \Drupal::service('page_cache_kill_switch')->trigger();

    return $preview;
  }

  /**
   * The title callback for the page that renders a single node in preview.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $oe_translation_request
   *   The current node.
   * @param string $language
   *   The language to preview in.
   *
   * @return string
   *   The page title.
   */
  public function previewRequestTitle(TranslationRequestInterface $oe_translation_request, string $language): string {
    // Retrieve the entity being translated and its data.
    $entity = $oe_translation_request->getContentEntity();
    if (!$entity) {
      throw new \Exception('There is no entity currently being translated.');
    }
    $data = $oe_translation_request->getData();

    // Generate preview with target translation data.
    $translation = $this->getTranslationPreview($entity, $data, $language);

    // Show the translated title on the translation preview page.
    return $translation->label();
  }

  /**
   * Builds the entity translation for the provided translation data.
   *
   * Defers to the parent TMGMT class, but caches statically the result.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which the translation should be returned.
   * @param array $data
   *   The translation data for the fields.
   * @param string $language
   *   The target language.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Translated entity.
   */
  protected function getTranslationPreview(ContentEntityInterface $entity, array $data, string $language): ContentEntityInterface {
    $translation = &drupal_static('translation_preview');
    if ($translation) {
      return $translation;
    }

    $translation = $this->makePreview($entity, $data, $language);
    return $translation;
  }

}
