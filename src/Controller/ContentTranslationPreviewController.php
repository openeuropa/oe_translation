<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Render\Element;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation\TranslationSourceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Content preview translation controller.
 */
class ContentTranslationPreviewController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The page cache kill switch.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $killSwitch;

  /**
   * The translation source manager.
   *
   * @var \Drupal\oe_translation\TranslationSourceManagerInterface
   */
  protected $translationSourceManager;

  /**
   * Creates an ContentTranslationPreviewController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $killSwitch
   *   The page cache kill switch.
   * @param \Drupal\oe_translation\TranslationSourceManagerInterface $translationSourceManager
   *   The translation source manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, KillSwitch $killSwitch, TranslationSourceManagerInterface $translationSourceManager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->killSwitch = $killSwitch;
    $this->translationSourceManager = $translationSourceManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('page_cache_kill_switch'),
      $container->get('oe_translation.translation_source_manager')
    );
  }

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
    $this->killSwitch->trigger();

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
