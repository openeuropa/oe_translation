<?php

declare(strict_types=1);

namespace Drupal\oe_translation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * EntityRevisionWithType constraint.
 *
 * @Constraint(
 *   id = "EntityRevisionWithType",
 *   label = @Translation("Entity Revision With Type", context = "Validation")
 * )
 */
class EntityRevisionWithTypeConstraint extends Constraint {

  /**
   * Message in case the entity type value is missing.
   *
   * @var string
   */
  public $missingEntityTypeMessage = "The entity type is missing.";

  /**
   * Message in case the entity type value is invalid.
   *
   * @var string
   */
  public $invalidEntityType = "The entity type is invalid.";

}
