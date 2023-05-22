<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the translation request entity add/edit forms.
 */
class TranslationRequestForm extends ContentEntityForm {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * Constructs a new instance of this class.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, RendererInterface $renderer) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);

    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => $this->renderer->render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New translation request %label has been created.', $message_arguments));
      $this->logger('oe_translation_request')->notice('Created new translation request %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The translation request %label has been updated.', $message_arguments));
      $this->logger('oe_translation_request')->notice('Updated new translation request %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.oe_translation_request.canonical', ['oe_translation_request' => $entity->id()]);
  }

  /**
   * Validates that the element is not longer than the max length.
   *
   * @param array $element
   *   The input element to validate.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @see TranslationFormTrait::translationFormElement()
   */
  public static function validateMaxLength(array $element, FormStateInterface &$form_state): void {
    // The value can be nested in a rare case in which the element is a
    // text_format.
    $value = $element['#value'] ?? $element['value']['#value'];
    if (isset($element['#max_length']) && ($element['#max_length'] < mb_strlen($value))) {
      $form_state->setError($element,
          t('The field has @size characters while the limit is @limit.', [
            '@size' => mb_strlen($element['#value']),
            '@limit' => $element['#max_length'],
          ])
        );
    }
  }

}
