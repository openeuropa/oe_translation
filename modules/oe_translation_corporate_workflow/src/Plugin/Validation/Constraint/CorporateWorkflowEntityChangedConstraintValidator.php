<?php

namespace Drupal\oe_translation_corporate_workflow\Plugin\Validation\Constraint;

use Drupal\Core\Entity\Plugin\Validation\Constraint\EntityChangedConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * Validates the CorporateWorkflowEntityChangedConstraint constraint.
 */
class CorporateWorkflowEntityChangedConstraintValidator extends EntityChangedConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    if (!isset($entity)) {
      return;
    }
    $workflow = \Drupal::service('content_moderation.moderation_information')->getWorkflowForEntity($entity);
    if (!$workflow || $workflow->id() !== 'oe_corporate_workflow') {
      // If the entity is not using the corporate workflow, we don't make any
      // change.
      parent::validate($entity, $constraint);
      return;
    }

    // The following is similar to the parent, except that it does not compare
    // the changed timestamp of translations but only of the source langauge.
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    if (!$entity->isNew()) {
      $saved_entity = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId())->loadUnchanged($entity->id());
      if ($saved_entity) {
        $common_translation_languages = array_intersect_key($entity->getTranslationLanguages(), $saved_entity->getTranslationLanguages());
        foreach (array_keys($common_translation_languages) as $langcode) {
          if (!$saved_entity->getTranslation($langcode)->isDefaultTranslation()) {
            continue;
          }
          if ($saved_entity->getTranslation($langcode)->getChangedTime() > $entity->getTranslation($langcode)->getChangedTime()) {
            $this->context->addViolation($constraint->message);
            break;
          }
        }
      }
    }
  }

}
