<?php

declare(strict_types=1);

namespace Drupal\oe_translation_active_revision\ParamConverter;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\ParamConverter\EntityConverter;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Symfony\Component\Routing\Route;

/**
 * Param converter for the active revision on the node.
 *
 * We need this because the entity view controller, after building the entity
 * render array and all the view and build hooks have passed, it just
 * replaces back the rendered entity with the initial one. Because normally,
 * for other view modes, we use
 * oe_translation_active_revision_entity_build_defaults_alter() to switch out
 * the entity revision.
 *
 * @see EntityViewController::view()
 */
class ActiveRevisionNodeConverter extends EntityConverter {

  use ActiveRevisionConverterTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The context repository.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository, LanguageManagerInterface $language_manager, ContextRepositoryInterface $contextRepository) {
    parent::__construct($entity_type_manager, $entity_repository);
    $this->languageManager = $language_manager;
    $this->contextRepository = $contextRepository;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    // Handle the case for the Node param only on the node canonical route and
    // the latest revision route.
    if (!empty($definition['type']) && str_starts_with($definition['type'], 'entity:')) {
      $entity_type_id = substr($definition['type'], strlen('entity:'));
      $paths = [
        '/node/{node}',
        '/node/{node}/latest',
      ];
      return $entity_type_id === 'node' && in_array($route->getPath(), $paths);
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $route_name = $defaults['_route'] ?? NULL;
    $routes = [
      'entity.node.canonical',
      // Even if the latest version loads a revision, the param converter for
      // this is the regular entity one.
      'entity.node.latest_version',
    ];
    if (!in_array($route_name, $routes)) {
      return parent::convert($value, $definition, $name, $defaults);
    }

    return $this->doConvert($value, $definition, $name, $defaults);
  }

}
