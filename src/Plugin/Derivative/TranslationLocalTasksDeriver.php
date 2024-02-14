<?php

declare(strict_types=1);

namespace Drupal\oe_translation\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_translation\TranslatorProvidersInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates the local tasks for our translation system.
 */
class TranslationLocalTasksDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The base plugin ID.
   *
   * @var string
   */
  protected $basePluginId;

  /**
   * The translation providers service.
   *
   * @var \Drupal\oe_translation\TranslatorProvidersInterface
   */
  protected $translatorProviders;

  /**
   * Creates a TranslationLocalTasks object.
   *
   * @param string $base_plugin_id
   *   The base plugin ID.
   * @param \Drupal\oe_translation\TranslatorProvidersInterface $translatorProviders
   *   The translation providers service.
   */
  public function __construct($base_plugin_id, TranslatorProvidersInterface $translatorProviders) {
    $this->basePluginId = $base_plugin_id;
    $this->translatorProviders = $translatorProviders;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('oe_translation.translator_providers'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    $entity_types = $this->translatorProviders->getDefinitions();
    foreach ($entity_types as $entity_type_id => $entity_type) {
      $this->derivatives["$entity_type_id.dashboard"] = [
        'route_name' => "entity.$entity_type_id.content_translation_overview",
        'title' => $this->t('Dashboard'),
        'parent_id' => "content_translation.local_tasks:entity.$entity_type_id.content_translation_overview",
        'weight' => 1,
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
