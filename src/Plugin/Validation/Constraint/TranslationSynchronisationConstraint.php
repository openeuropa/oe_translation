<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * TranslationSynchronisation constraint.
 *
 * @Constraint(
 *   id = "TranslationSynchronisation",
 *   label = @Translation("Translation Synchronisation", context = "Validation")
 * )
 */
class TranslationSynchronisationConstraint extends Constraint {

  /**
   * Message shown when no language was selected.
   *
   * @var string
   */
  public $missingLanguages = "Select at least one language to be approved for synchronizing.";

}
