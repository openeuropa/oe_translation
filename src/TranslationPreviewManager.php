<?php

declare(strict_types=1);

namespace Drupal\oe_translation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\oe_translation\Entity\TranslationRequestInterface;

/**
 * Handles the generation of a node preview from a given translation request.
 */
class TranslationPreviewManager implements TranslationPreviewManagerInterface {

  /**
   * The translation source manager.
   *
   * @var \Drupal\oe_translation\TranslationSourceManagerInterface
   */
  protected $translationSourceManager;

  /**
   * Creates an TranslationPreviewManager object.
   *
   * @param \Drupal\oe_translation\TranslationSourceManagerInterface $translationSourceManager
   *   The translation source manager.
   */
  public function __construct(TranslationSourceManagerInterface $translationSourceManager) {
    $this->translationSourceManager = $translationSourceManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslation(TranslationRequestInterface $request, string $language): ContentEntityInterface {
    $data = $this->extractPreviewData($request, $language);
    $entity = $request->getContentEntity();
    return $this->getTranslationPreview($entity, $data, $language, $request);
  }

  /**
   * Extracts required data from the request to build its preview.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $oe_translation_request
   *   The translation request.
   * @param string $language
   *   The language to preview in.
   *
   * @return array
   *   The translation data.
   */
  protected function extractPreviewData(TranslationRequestInterface $oe_translation_request, string $language): array {
    // Retrieve the entity being translated and its data.
    $entity = $oe_translation_request->getContentEntity();
    if (!$entity) {
      throw new \Exception('There is no entity currently being translated.');
    }

    return $oe_translation_request->getData();
  }

  /**
   * Builds the entity translation for the provided translation data.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which the translation should be returned.
   * @param array $data
   *   The translation data for the fields.
   * @param string $language
   *   The language to preview in.
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $request
   *   The translation request.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Translated entity.
   */
  protected function getTranslationPreview(ContentEntityInterface $entity, array $data, string $language, TranslationRequestInterface $request): ContentEntityInterface {
    $translation = &drupal_static('translation_preview');
    if ($translation) {
      return $translation;
    }

    return $this->makePreview($entity, $data, $language, $request);
  }

  /**
   * Builds the entity translation for the provided translation data.
   *
   * For this, we use the translation source manager data saving method but
   * we pass FALSE for the SAVE flag.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which the translation should be returned.
   * @param array $data
   *   The translation data for the fields.
   * @param string $target_langcode
   *   The target language.
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $request
   *   The translation request.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The translated entity.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected function makePreview(ContentEntityInterface $entity, array $data, string $target_langcode, TranslationRequestInterface $request): ContentEntityInterface {
    return $this->translationSourceManager->saveData($data, $entity, $target_langcode, FALSE, $request->getData());
  }

}
