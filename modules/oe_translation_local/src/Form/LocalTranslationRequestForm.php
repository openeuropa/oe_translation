<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_local\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\filter\Entity\FilterFormat;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation\Form\TranslationRequestForm;
use Drupal\oe_translation\TranslationSourceManagerInterface;
use Drupal\tmgmt\Data;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the translation request entity add/edit forms.
 */
class LocalTranslationRequestForm extends TranslationRequestForm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The TMGMT data service.
   *
   * @var \Drupal\tmgmt\Data
   */
  protected $tmgmtData;

  /**
   * The translation source manager.
   *
   * @var \Drupal\oe_translation\TranslationSourceManagerInterface
   */
  protected $translationSourceManager;

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, EntityTypeManagerInterface $entity_type_manager, Data $tmgmt_data, TranslationSourceManagerInterface $translation_source_manager, AccountInterface $current_user) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->entityTypeManager = $entity_type_manager;
    $this->tmgmtData = $tmgmt_data;
    $this->translationSourceManager = $translation_source_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity_type.manager'),
      $container->get('tmgmt.data'),
      $container->get('oe_translation.translation_source_manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['translation'] = [
      '#type' => 'container',
    ];

    /** @var \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request */
    $translation_request = $this->entity;
    $langcode = $translation_request->getTargetLanguageCodes()[0];
    $existing_translation_data = [];

    // Get the data from the translation request. It may happen we are editing
    // one that was saved and it has data. If it doesn't, we check if the
    // revision we started from has already a translation and we merge the
    // translation values.
    $data = $translation_request->getData();
    if (!$this->hasAnyTranslations($data)) {
      $entity = $translation_request->getContentEntity();
      $existing_translation = $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : NULL;
      $existing_translation_data = $existing_translation ? $this->translationSourceManager->extractData($existing_translation) : [];
    }

    // Disable the element if the translation request is accepted.
    $disable = $translation_request->getRequestStatus() === TranslationRequestInterface::STATUS_ACCEPTED;
    // Need to keep the first hierarchy. So the flattening must take place
    // inside the foreach loop.
    foreach (Element::children($data) as $key) {
      $data_flattened = $this->tmgmtData->flatten($data[$key], $key);
      $existing_translation_data_flattened = $existing_translation_data ? $this->tmgmtData->flatten($existing_translation_data[$key], $key) : [];
      $form['translation'][$key] = $this->translationFormElement($data_flattened, $existing_translation_data_flattened, $disable);
    }

    return $form;
  }

  /**
   * Renders a form element for each piece of translatable data.
   *
   * @param array $data
   *   The data.
   * @param array $existing_translation_data
   *   Existing entity translation data.
   * @param bool $disable
   *   Whether to disable all translation elements.
   *
   * @return array
   *   The form element.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  protected function translationFormElement(array $data, array $existing_translation_data, bool $disable) {
    $element = [];

    foreach (Element::children($data) as $key) {
      if (!isset($data[$key]['#text']) || !$this->tmgmtData->filterData($data[$key])) {
        continue;
      }

      // The char sequence '][' confuses the form API so we need to replace
      // it.
      $target_key = str_replace('][', '|', $key);
      $element[$target_key] = [
        '#tree' => TRUE,
        '#theme' => 'local_translation_form_element_group',
        '#ajaxid' => Html::getUniqueId('tmgmt-local-element-' . $key),
        '#parent_label' => $data[$key]['#parent_label'],
        '#zebra' => '',
      ];

      // Manage the height of the texteareas, depending on the length of the
      // description. The minimum number of rows is 3 and the maximum is 15.
      $rows = ceil(strlen($data[$key]['#text']) / 100);
      if ($rows < 3) {
        $rows = 3;
      }
      elseif ($rows > 15) {
        $rows = 15;
      }
      $element[$target_key]['source'] = [
        '#type' => 'textarea',
        '#value' => $data[$key]['#text'],
        '#title' => $this->t('Source'),
        '#disabled' => TRUE,
        '#rows' => $rows,
      ];

      if (empty($existing_translation_data)) {
        // If we don't have existing translation data already passed, we
        // default to whatever there is in the original data. If the original
        // data doesn't have translation values, we fill them in with the
        // source.
        $translation_value = $data[$key]['#translation']['#text'] ?? $data[$key]['#text'];
      }
      else {
        // Otherwise, we fish it out from the source values of the
        // existing translation data.
        $translation_value = $existing_translation_data[$key]['#text'] ?? NULL;
      }

      $element[$target_key]['translation'] = [
        '#type' => 'textarea',
        '#default_value' => $translation_value,
        '#title' => $this->t('Translation'),
        '#rows' => $rows,
        '#allow_focus' => !$disable,
        '#disabled' => $disable,
      ];

      // Check if the field has a text format and ensure the element uses one
      // too (if the user has access to it).
      $format_id = $data[$key]['#format'] ?? NULL;
      if (!$format_id) {
        continue;
      }

      /** @var \Drupal\filter\Entity\FilterFormat $format */
      $format = FilterFormat::load($format_id);

      if ($format && $format->access('use')) {
        // In case a user has permission to translate the content using
        // selected text format, add a format id into the list of allowed
        // text formats. Otherwise, no text format will be used.
        $element[$target_key]['source']['#allowed_formats'] = [$format_id];
        $element[$target_key]['translation']['#allowed_formats'] = [$format_id];
        $element[$target_key]['source']['#type'] = 'text_format';
        $element[$target_key]['translation']['#type'] = 'text_format';
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request */
    $translation_request = $this->entity;

    $actions['save_as_draft'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#submit' => ['::submitForm', '::markDraft', '::save'],
      '#value' => $this->t('Save as draft'),
    ];

    $actions['save_and_accept'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#submit' => ['::submitForm', '::accept', '::save'],
      '#access' => $translation_request->getRequestStatus() === TranslationRequestInterface::STATUS_DRAFT && $this->currentUser->hasPermission('accept translation request'),
      '#value' => $this->t('Save and accept'),
    ];

    $actions['save_and_sync'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#submit' => ['::submitForm', '::save', '::synchronise'],
      '#access' => $translation_request->getRequestStatus() !== TranslationRequestInterface::STATUS_SYNCHRONISED && $this->currentUser->hasPermission('sync translation request'),
      '#value' => $this->t('Save and synchronise'),
    ];

    $actions['preview'] = [
      '#type' => 'submit',
      '#button_type' => 'secondary',
      // We need to save the values into the translation before redirecting to
      // the preview page so we have something to preview.
      '#submit' => ['::submitForm', '::save', '::preview'],
      '#value' => $this->t('Preview'),
    ];

    if (!$this->entity->isNew() && $this->entity->hasLinkTemplate('delete-form')) {
      $route_info = $this->entity->toUrl('delete-form');
      $query = $route_info->getOption('query');
      $entity = $translation_request->getContentEntity();
      $entity_type_id = $entity->getEntityTypeId();
      $query['destination'] = Url::fromRoute("entity.$entity_type_id.local_translation", [$entity_type_id => $entity->id()])->toString();
      $route_info->setOption('query', $query);
      $actions['delete'] = [
        '#type' => 'link',
        '#title' => $this->t('Delete'),
        '#access' => $this->entity->access('delete'),
        '#attributes' => [
          'class' => ['button', 'button--danger'],
        ],
      ];
      $actions['delete']['#url'] = $route_info;
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request */
    $translation_request = $this->entity;

    $data = $translation_request->getData();
    foreach ($form_state->getValues() as $key => $value) {
      if (is_array($value) && isset($value['translation'])) {
        // Update the translation, this will only update the translation in case
        // it has changed. We have two different cases, the first is for nested
        // texts.
        if (is_array($value['translation'])) {
          $update['#translation']['#text'] = $value['translation']['value'];
        }
        else {
          $update['#translation']['#text'] = $value['translation'];
        }
        $data = $this->updateData($key, $data, $update);
      }
    }

    $translation_request->setData($data);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request */
    $translation_request = $this->entity;

    $translation_request->save();
    $this->messenger()->addStatus($this->t('The translation request has been saved.'));

    $this->addRedirect($form_state);
  }

  /**
   * Sets the status to accepted.
   *
   * Callback for the "Save and accept" button. It gets followed by save.
   */
  public function accept(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request */
    $translation_request = $this->entity;
    $translation_request->setRequestStatus(TranslationRequestInterface::STATUS_ACCEPTED);
  }

  /**
   * Sets the status to draft.
   *
   * Callback for the "Save as draft" button. It gets followed by save.
   */
  public function markDraft(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request */
    $translation_request = $this->entity;
    $translation_request->setRequestStatus(TranslationRequestInterface::STATUS_DRAFT);
  }

  /**
   * Synchronises the content.
   *
   * Callback for the "Save and accept" button. It gets followed by save.
   */
  public function synchronise(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request */
    $translation_request = $this->entity;

    $entity = $translation_request->getContentEntity();
    $saved = $this->translationSourceManager->saveData($translation_request->getData(), $entity, $translation_request->getTargetLanguageCodes()[0]);
    if ($saved) {
      $translation_request->setRequestStatus(TranslationRequestInterface::STATUS_SYNCHRONISED);
      $translation_request->save();
      $this->messenger()->addStatus($this->t('The translation has been synchronised.'));
      return;
    }

    $this->messenger()->addError($this->t('There was a problem synchronising the translation.'));
  }

  /**
   * Redirects the user to the preview path of the translation request.
   */
  public function preview(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request */
    $translation_request = $this->entity;

    // For local translations, we only have 1 language.
    $languages = $translation_request->getTargetLanguageCodes();
    $language = reset($languages);
    $url = $translation_request->toUrl('preview');
    $url->setRouteParameter('language', $language);
    $form_state->setRedirectUrl($url);
  }

  /**
   * Updates the values for a specific substructure in the data array.
   *
   * The values are either set or updated but never deleted.
   *
   * Copied from TMGMT from the JobItem.
   *
   * @param string|array $key
   *   Key pointing to the item the values should be applied.
   *   The key can be either be an array containing the keys of a nested array
   *   hierarchy path or a string with '][' or '|' as delimiter.
   * @param array $data
   *   The entire data set.
   * @param array $values
   *   Nested array of values to set.
   * @param bool $replace
   *   (optional) When TRUE, replaces the structure at the provided key instead
   *   of writing into it.
   *
   * @return array
   *   The updated data.
   */
  protected function updateData($key, array $data, array $values, bool $replace = FALSE) {
    if ($replace) {
      NestedArray::setValue($data, $this->tmgmtData->ensureArrayKey($key), $values);
    }
    foreach ($values as $index => $value) {
      // In order to preserve existing values, we can not apply the values array
      // at once. We need to apply each containing value on its own.
      // If $value is an array we need to advance the hierarchy level.
      if (is_array($value)) {
        $data = $this->updateData(array_merge($this->tmgmtData->ensureArrayKey($key), [$index]), $data, $value);
      }
      // Apply the value.
      else {
        NestedArray::setValue($data, array_merge($this->tmgmtData->ensureArrayKey($key), [$index]), $value, TRUE);
      }
    }

    return $data;
  }

  /**
   * Adds a redirect back to where we came from.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function addRedirect(FormStateInterface $form_state): void {
    /** @var \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request */
    $translation_request = $this->entity;
    $entity = $translation_request->getContentEntity();
    $entity_type_id = $entity->getEntityTypeId();
    $url = Url::fromRoute("entity.$entity_type_id.local_translation", [$entity_type_id => $entity->id()]);
    $form_state->setRedirectUrl($url);
  }

  /**
   * Checks if any of the source data elements have translation values.
   *
   * If at least one has, it indicates that the data set was translated.
   *
   * @param array $data
   *   The data array.
   *
   * @return bool
   *   Whether at least one of the fields was translated.
   */
  protected function hasAnyTranslations(array $data): bool {
    $flattened = $this->tmgmtData->flatten($data);
    foreach ($flattened as $value) {
      if (isset($value['#translation'])) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
