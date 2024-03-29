<?php

declare(strict_types=1);

namespace Drupal\oe_translation_active_revision\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the active revision entity edit forms.
 */
class ActiveRevisionForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);

    $entity = $this->getEntity();

    $message_arguments = ['%label' => $entity->toLink()->toString()];
    $logger_arguments = [
      '%label' => $entity->label(),
      'link' => $entity->toLink($this->t('View'))->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New active revision %label has been created.', $message_arguments));
        $this->logger('oe_translation_active_revision')->notice('Created new active revision %label', $logger_arguments);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The active revision %label has been updated.', $message_arguments));
        $this->logger('oe_translation_active_revision')->notice('Updated active revision %label.', $logger_arguments);
        break;
    }

    $form_state->setRedirect('entity.oe_translation_active_revision.collection');

    return $result;
  }

}
