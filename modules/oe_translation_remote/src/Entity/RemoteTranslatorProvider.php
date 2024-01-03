<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the RemoteTranslatorProvider configuration entity.
 *
 * @ConfigEntityType(
 *   id = "remote_translation_provider",
 *   label = @Translation("Remote Translator Provider"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\oe_translation_remote\Form\RemoteTranslatorProviderForm",
 *       "edit" = "Drupal\oe_translation_remote\Form\RemoteTranslatorProviderForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\oe_translation_remote\RemoteTranslatorProviderListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer remote translators",
 *   config_prefix = "remote_translation_provider",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/remote-translation-provider/add",
 *     "edit-form" = "/admin/structure/remote-translation-provider/{remote_translation_provider}/edit",
 *     "delete-form" = "/admin/structure/remote-translation-provider/{remote_translation_provider}/delete",
 *     "collection" = "/admin/structure/remote-translation-provider",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "plugin",
 *     "plugin_configuration",
 *     "enabled"
 *   }
 * )
 */
class RemoteTranslatorProvider extends ConfigEntityBase implements RemoteTranslatorProviderInterface {

  /**
   * The plugin id.
   *
   * @var string
   */
  protected $id;

  /**
   * The plugin label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Remote translation provider plugin.
   *
   * @var string
   */
  protected $plugin;

  /**
   * The Remote translation provider plugin settings.
   *
   * @var array
   */
  protected $plugin_configuration;

  /**
   * Whether the translator is enabled.
   *
   * @var bool
   */
  protected $enabled = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getProviderPlugin(): ?string {
    return $this->plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function setProviderPlugin(string $plugin): RemoteTranslatorProviderInterface {
    $this->plugin = $plugin;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProviderConfiguration(): ?array {
    return $this->plugin_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setProviderConfiguration(array $configuration): RemoteTranslatorProviderInterface {
    $this->plugin_configuration = $configuration;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return (bool) $this->enabled;
  }

  /**
   * {@inheritdoc}
   */
  public function setEnabled($enabled): RemoteTranslatorProviderInterface {
    $this->enabled = (bool) $enabled;
    return $this;
  }

}
