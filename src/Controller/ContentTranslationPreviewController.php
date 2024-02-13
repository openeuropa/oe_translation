<?php

declare(strict_types=1);

namespace Drupal\oe_translation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation\TranslationPreviewManagerInterface;
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
   * The translation preview manager.
   *
   * @var \Drupal\oe_translation\TranslationPreviewManagerInterface
   */
  protected $previewManager;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Creates an ContentTranslationPreviewController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $killSwitch
   *   The page cache kill switch.
   * @param \Drupal\oe_translation\TranslationPreviewManagerInterface $previewManager
   *   The translation preview manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, KillSwitch $killSwitch, TranslationPreviewManagerInterface $previewManager, RouteMatchInterface $routeMatch) {
    $this->entityTypeManager = $entity_type_manager;
    $this->killSwitch = $killSwitch;
    $this->previewManager = $previewManager;
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('page_cache_kill_switch'),
      $container->get('oe_translation.content_translation_preview_manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * Preview the translation request entity underlying translation data.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $oe_translation_request
   *   The translation request.
   * @param string $language
   *   The language to preview in.
   *
   * @return array
   *   A render of the translated entity.
   */
  public function previewRequest(TranslationRequestInterface $oe_translation_request, string $language): array {
    $translation = $this->previewManager->getTranslation($oe_translation_request, $language);
    $translation->in_preview = TRUE;

    // Set the current translation into the current route so it can be
    // accessible by others.
    $this->routeMatch->getRouteObject()->setDefault('translation_preview_entity', $translation);

    // Build view for entity.
    $entity = $oe_translation_request->getContentEntity();
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
   *   The translation request.
   * @param string $language
   *   The language to preview in.
   *
   * @return string
   *   The page title.
   */
  public function previewRequestTitle(TranslationRequestInterface $oe_translation_request, string $language): string {
    $translation = $this->previewManager->getTranslation($oe_translation_request, $language);
    return $translation->label();
  }

}
