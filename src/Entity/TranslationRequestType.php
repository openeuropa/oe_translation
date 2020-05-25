<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Translation Request type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "oe_translation_request_type",
 *   label = @Translation("Translation Request type"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\oe_translation\Form\TranslationRequestTypeForm",
 *       "edit" = "Drupal\oe_translation\Form\TranslationRequestTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\oe_translation\TranslationRequestTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer translation request types",
 *   bundle_of = "oe_translation_request",
 *   config_prefix = "oe_translation_request_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/oe_translation_request_types/add",
 *     "edit-form" = "/admin/structure/oe_translation_request_types/manage/{oe_translation_request_type}",
 *     "delete-form" = "/admin/structure/oe_translation_request_types/manage/{oe_translation_request_type}/delete",
 *     "collection" = "/admin/structure/oe_translation_request_types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *   }
 * )
 */
class TranslationRequestType extends ConfigEntityBundleBase {

  /**
   * The machine name of this translation request type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the translation request type.
   *
   * @var string
   */
  protected $label;

}
