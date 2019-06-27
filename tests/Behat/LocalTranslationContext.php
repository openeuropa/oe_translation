<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Behat;

use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Context specific to TMGMT-based local translation.
 */
class LocalTranslationContext extends RawDrupalContext {

  /**
   * Create a translatable node.
   *
   * @param string $title
   *   The title.
   * @param string $body
   *   The body.
   *
   * @Given a translatable node with the :title title and :body body
   */
  public function aTranslatableNodeWithTheTitleAndBody(string $title, string $body): void {
    $node = (object) [
      'type' => 'oe_demo_translatable_page',
      'title' => $title,
      'field_oe_demo_translatable_body' => $body,
    ];

    $this->nodeCreate($node);
  }

  /**
   * Checks that a translation form element contains a value.
   *
   * @param string $field
   *   The field.
   * @param string $value
   *   The value.
   *
   * @Then the translation form element for the :field field should contain :value
   */
  public function translationElementForFieldContains(string $field, string $value): void {
    $selector = $this->getElementSelectorForField($field);
    $this->assertSession()->elementContains('css', $selector, $value);
  }

  /**
   * Fills in the translation field.
   *
   * @param string $field
   *   The field.
   * @param string $value
   *   The value.
   *
   * @When I fill in the translation form element for the :field field with :value
   */
  public function fillInTranslationFormElement(string $field, string $value) {
    $selector = $this->getElementSelectorForField($field);
    $element = $this->getSession()->getPage()->find('css', $selector);
    if (!$element) {
      throw new \Exception('Field not found on the page.');
    }

    $this->getSession()->getPage()->fillField($element->getAttribute('id'), $value);
  }

  /**
   * Determines the selector on the TMGMT local translation form for a field.
   *
   * @param string $field
   *   The field.
   *
   * @return string
   *   The selector.
   */
  protected function getElementSelectorForField(string $field): string {
    /** @var \Drupal\tmgmt_content\Plugin\tmgmt\Source\ContentEntitySource $plugin */
    $plugin = \Drupal::service('plugin.manager.tmgmt.source')->createInstance('content');
    /** @var \Drupal\node\NodeInterface $node */
    $node = \Drupal::entityTypeManager()->getStorage('node')->create([
      'type' => 'oe_demo_translatable_page',
      'title' => 'Dummy',
      'field_oe_demo_translatable_body' => 'Dummy',
    ]);

    $field_name = NULL;
    foreach ($node->getFieldDefinitions() as $name => $definition) {
      if ((string) $definition->getLabel() === $field) {
        $field_name = $name;
        break;
      }
    }

    if (!$field_name) {
      throw new \Exception('Field not found.');
    }

    $translatable_data = $plugin->extractTranslatableData($node);
    if (!isset($translatable_data[$field_name])) {
      throw new \Exception('Field not found.');
    }

    $flat = \Drupal::service('tmgmt.data')->flatten($translatable_data[$field_name]);
    $key = $field_name . '|' . str_replace('][', '|', key($flat)) . '[translation]';

    return 'textarea[name*="' . $key . '"]';
  }

}
