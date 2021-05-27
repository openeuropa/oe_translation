<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\tmgmt\Form\JobItemAbortForm as OriginalJobItemAbortForm;
use Drupal\tmgmt\JobItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the job item abort form depending on the translator used.
 *
 * Allows for individual translator plugins to specify in their annotation
 * which form class to use for the abort form. These need to extend the default
 * JobItemAbortForm class.
 */
class JobItemAbortController extends ControllerBase {

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * JobItemAbortController constructor.
   *
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $classResolver
   *   The class resolver.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The string translation service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   */
  public function __construct(ClassResolverInterface $classResolver, TranslationInterface $stringTranslation, ModuleHandlerInterface $moduleHandler, EntityTypeManagerInterface $entityTypeManager, FormBuilderInterface $formBuilder) {
    $this->classResolver = $classResolver;
    $this->stringTranslation = $stringTranslation;
    $this->moduleHandler = $moduleHandler;
    $this->entityTypeManager = $entityTypeManager;
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('class_resolver'),
      $container->get('string_translation'),
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('form_builder')
    );
  }

  /**
   * Builds the job item abort form for a given job item.
   *
   * @param \Drupal\tmgmt\JobItemInterface $tmgmt_job_item
   *   The job item.
   *
   * @return array
   *   The form.
   */
  public function form(JobItemInterface $tmgmt_job_item): array {
    $class = $this->getFormClass($tmgmt_job_item);
    $form_object = $this->classResolver->getInstanceFromDefinition($class);

    // The entity form objects require a few dependencies.
    // @see EntityTypeManager::getFormObject().
    $form_object
      ->setStringTranslation($this->stringTranslation)
      ->setModuleHandler($this->moduleHandler)
      ->setEntityTypeManager($this->entityTypeManager)
      ->setOperation('abort')
        // The entity manager cannot be injected due to a circular dependency.
        // @todo Remove this set call in https://www.drupal.org/node/2603542.
      ->setEntityManager(\Drupal::service('entity_type.manager'));

    $form_object->setEntity($tmgmt_job_item);

    $form_state = (new FormState())->setFormState([]);
    return $this->formBuilder()->buildForm($form_object, $form_state);
  }

  /**
   * Determines the abort form class for a given job item.
   *
   * It does so by inspecting the the plugin definition of the translator
   * used by the job item and falling back to the default TMGMT abort form
   * class.
   *
   * @param \Drupal\tmgmt\JobItemInterface $tmgmt_job_item
   *   The job item.
   *
   * @return string
   *   The form class.
   */
  protected function getFormClass(JobItemInterface $tmgmt_job_item): string {
    $translator = $tmgmt_job_item->getTranslator()->getPluginId();
    $definition = \Drupal::service('plugin.manager.tmgmt.translator')->getDefinition($translator);
    if (isset($definition['abort_class'])) {
      return $definition['abort_class'];
    }

    return OriginalJobItemAbortForm::class;
  }

}
