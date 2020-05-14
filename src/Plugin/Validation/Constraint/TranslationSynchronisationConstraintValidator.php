<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the TranslationSynchronisationConstraint.
 */
class TranslationSynchronisationConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (empty($value['languages'])) {
      return $this->context->addViolation($constraint->missingLanguages);
    }
  }

}
