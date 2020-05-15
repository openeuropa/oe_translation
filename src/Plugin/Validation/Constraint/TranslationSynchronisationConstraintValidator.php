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
    if ($value->get('type')->getValue() !== 'automatic') {
      // We don't have any validation if it's not an automatic.
      return;
    }

    // If it is, we need to ensure we have some languages selected.
    $configuration = $value->get('configuration')->getValue();
    if (!$configuration || !isset($configuration['languages']) || empty($configuration['languages'])) {
      $this->context->addViolation($constraint->missingLanguages);
    }
  }

}
