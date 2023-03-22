<?php

declare(strict_types=1);

namespace Drupal\oe_translation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Render\Element;
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
    return $this->getTranslationPreview($entity, $data, $language);
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
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Translated entity.
   */
  protected function getTranslationPreview(ContentEntityInterface $entity, array $data, string $language): ContentEntityInterface {
    $translation = &drupal_static('translation_preview');
    if ($translation) {
      return $translation;
    }

    return $this->makePreview($entity, $data, $language);
  }

  /**
   * Builds the entity translation for the provided translation data.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which the translation should be returned.
   * @param array $data
   *   The translation data for the fields.
   * @param string $target_langcode
   *   The target language.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The translated entity.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected function makePreview(ContentEntityInterface $entity, array $data, string $target_langcode): ContentEntityInterface {
    // If the translation for this language does not exist yet, initialize it.
    if (!$entity->hasTranslation($target_langcode)) {
      $entity->addTranslation($target_langcode, $entity->toArray());
    }

    $embeddable_fields = $this->translationSourceManager->getEmbeddableFields($entity);

    $translation = $entity->getTranslation($target_langcode);

    foreach (Element::children($data) as $name) {
      $field_data = $data[$name];
      foreach (Element::children($field_data) as $delta) {
        $field_item = $field_data[$delta];
        foreach (Element::children($field_item) as $property) {
          $property_data = $field_item[$property];
          // If there is translation data for the field property, save it.
          if (isset($property_data['#translation']['#text']) && $property_data['#translate'] && is_numeric($delta)) {
            $item = $translation->get($name)->offsetGet($delta);
            if ($item) {
              // @todo see if we should rely on the field processors instead.
              $translation->get($name)
                ->offsetGet($delta)
                ->set($property, $property_data['#translation']['#text']);
            }
          }
          // If the field is an embeddable reference, we assume that the
          // property is a field reference. The translation will be available
          // to formatters due to the static entity caching.
          elseif (isset($embeddable_fields[$name]) && $property === 'entity') {
            $this->makePreview($translation->get($name)->offsetGet($delta)->$property, $property_data, $target_langcode);
          }
        }
      }
    }

    return $translation;
  }

}
