<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\filter\Entity\FilterFormat;
use Drupal\tmgmt\Entity\JobItem;
use Drupal\tmgmt\SourcePreviewInterface;
use Drupal\tmgmt_local\Entity\LocalTaskItem;

/**
 * Form handler for the translation request entity add/edit forms.
 */
class LocalTranslationRequestForm extends TranslationRequestForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['translation'] = array(
      '#type' => 'container',
    );

    // Build the translation form.
    $data = Json::decode($this->entity->get('field_data')->value);

    // Need to keep the first hierarchy. So flatten must take place inside
    // of the foreach loop.
    $zebra = 'even';
    foreach (Element::children($data) as $key) {
      $flattened = \Drupal::service('tmgmt.data')->flatten($data[$key], $key);
      $form['translation'][$key] = $this->formElement($flattened, $zebra);
    }

    return $form;
  }

  private function formElement(array $data,  &$zebra) {
    static $flip = array(
      'even' => 'odd',
      'odd' => 'even',
    );

    $form = [];

    foreach (Element::children($data) as $key) {
      if (isset($data[$key]['#text']) && \Drupal::service('tmgmt.data')
          ->filterData($data[$key])) {
        // The char sequence '][' confuses the form API so we need to replace
        // it.
        $target_key = str_replace('][', '|', $key);
        $zebra = $flip[$zebra];
        $form[$target_key] = array(
          '#tree' => TRUE,
          '#theme' => 'tmgmt_local_translation_form_element',
          '#ajaxid' => Html::getUniqueId('tmgmt-local-element-' . $key),
          '#parent_label' => $data[$key]['#parent_label'],
          '#zebra' => $zebra,
        );

        // Manage the height of the texteareas, depending on the lenght of the
        // description. The minimum number of rows is 3 and the maximum is 15.
        $rows = ceil(strlen($data[$key]['#text']) / 100);
        if ($rows < 3) {
          $rows = 3;
        }
        elseif ($rows > 15) {
          $rows = 15;
        }
        $form[$target_key]['source'] = [
          '#type' => 'textarea',
          '#value' => $data[$key]['#text'],
          '#title' => t('Source'),
          '#disabled' => TRUE,
          '#rows' => $rows,
        ];

        $form[$target_key]['translation'] = [
          '#type' => 'textarea',
          '#default_value' => isset($data[$key]['#translation']['#text']) ? $data[$key]['#translation']['#text'] : NULL,
          '#title' => t('Translation'),
          '#rows' => $rows,
          '#allow_focus' => TRUE,
        ];
        if (!empty($data[$key]['#format']) && \Drupal::config('tmgmt.settings')
            ->get('respect_text_format') == '1') {
          $format_id = $data[$key]['#format'];
          /** @var \Drupal\filter\Entity\FilterFormat $format */
          $format = FilterFormat::load($format_id);

          if ($format && $format->access('use')) {
            // In case a user has permission to translate the content using
            // selected text format, add a format id into the list of allowed
            // text formats. Otherwise, no text format will be used.
            $form[$target_key]['source']['#allowed_formats'] = [$format_id];
            $form[$target_key]['translation']['#allowed_formats'] = [$format_id];
            $form[$target_key]['source']['#type'] = 'text_format';
            $form[$target_key]['translation']['#type'] = 'text_format';
          }
        }
      }
    }

    return $form;
  }

  public function save(array $form, FormStateInterface $form_state) {
    $data = Json::decode($this->entity->get('field_data')->value);

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

    $this->entity->set('field_data', Json::encode($data));
    $this->addToNode($data);
    parent::save($form, $form_state);
  }

  protected function updateData($key, $data, $values, $replace = FALSE) {
    if ($replace) {
      NestedArray::setValue($data, \Drupal::service('tmgmt.data')->ensureArrayKey($key), $values);
    }
    foreach ($values as $index => $value) {
     // In order to preserve existing values, we can not aplly the values array
     // at once. We need to apply each containing value on its own.
     // If $value is an array we need to advance the hierarchy level.
     if (is_array($value)) {
       $data = $this->updateData(array_merge(\Drupal::service('tmgmt.data')->ensureArrayKey($key), array($index)), $data, $value);
     }
     // Apply the value.
     else {
       NestedArray::setValue($data, array_merge(\Drupal::service('tmgmt.data')->ensureArrayKey($key), array($index)), $value, TRUE);
     }
    }

    return $data;
  }

  protected function addToNode($data) {
    /** @var \Drupal\oe_translation\Entity\TranslationRequest $request */
    $request = $this->entity;
    $node = $request->getContentEntity();
    $job_item = JobItem::create([]);
    $job_item->set('data', Json::encode($data));
    $job_item->translatable_entity = $node;
    $job_item->set('item_type', 'node');
    $job_item->set('item_id', 0);
    /** @var \Drupal\tmgmt_content\Plugin\tmgmt\Source\ContentEntitySource $plugin */
    $plugin = \Drupal::service('plugin.manager.tmgmt.source')->createInstance('content');
    $plugin->saveTranslation($job_item, $request->getTargetLanguageCodes()[0]);
  }

  /**
   * Prepare the date to be added to the JobItem.
   *
   * Right now JobItem looks for ['#text'] so if we send our structure it will
   * add as translation text our original text, so we are replacing ['#text']
   * with ['#translation']['#text']
   *
   * @param array $data
   *   The data items.
   *
   * @return array
   *   Returns the data items ready to be added to the JobItem.
   */
  protected function prepareData(array $data) {
    if (isset($data['#text'])) {
      if (isset($data['#translation']['#text'])) {
        $result['#text'] = $data['#translation']['#text'];
      }
      else {
        $result['#text'] = '';
      }
      return $result;
    }
    foreach (Element::children($data) as $key) {
      $data[$key] = $this->prepareData($data[$key]);
    }
    return $data;
  }


}
