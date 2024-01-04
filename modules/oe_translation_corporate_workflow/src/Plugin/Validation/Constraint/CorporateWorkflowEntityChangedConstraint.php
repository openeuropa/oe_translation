<?php

namespace Drupal\oe_translation_corporate_workflow\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\EntityChangedConstraint;

/**
 * Validation constraint for the corporate workflow entity changed timestamp.
 *
 * @Constraint(
 *   id = "CorporateWorkflowEntityChanged",
 *   label = @Translation("Corporate workflow entity changed", context = "Validation"),
 *   type = {"entity"}
 * )
 */
class CorporateWorkflowEntityChangedConstraint extends EntityChangedConstraint {
}
