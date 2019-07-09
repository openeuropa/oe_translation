<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Behat;

use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Behat\Hook\Scope\BeforeFeatureScope;
use Behat\Mink\Element\NodeElement;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use PHPUnit\Framework\Assert;

/**
 * Context specific to TMGMT-based local translation.
 */
class LocalTranslationContext extends RawDrupalContext {

  /**
   * Installs the test module.
   *
   * @param \Behat\Behat\Hook\Scope\BeforeFeatureScope $scope
   *   The scope.
   *
   * @BeforeFeature @translation
   */
  public static function installTestModule(BeforeFeatureScope $scope) {
    \Drupal::service('module_installer')->install(['oe_translation_test']);
  }

  /**
   * Uninstalls the test module.
   *
   * @param \Behat\Behat\Hook\Scope\AfterFeatureScope $scope
   *   The scope.
   *
   * @AfterFeature @translation
   */
  public static function uninstallTestModule(AfterFeatureScope $scope) {
    \Drupal::service('module_installer')->uninstall(['oe_translation_test']);
  }

  /**
   * Create a translatable node.
   *
   * @param string $title
   *   The title.
   * @param string $body
   *   The body.
   *
   * @Given a translatable node with the :title title and :body body and multiple links
   */
  public function translatableNodeWithTitleAndBody(string $title, string $body): void {
    $values = [
      'type' => 'oe_demo_translatable_page',
      'title' => $title,
      'field_oe_demo_translatable_body' => $body,
      'demo_link_field' => 'Example - https://example.com, Node - /node',
    ];

    $this->nodeCreate((object) $values);
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
    $element = $this->getTranslationElementForField($field);
    Assert::assertEquals($value, $element->getText());
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
    $element = $this->getTranslationElementForField($field);
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
   * @return \Behat\Mink\Element\NodeElement
   *   The selector.
   */
  protected function getTranslationElementForField(string $field): ?NodeElement {
    /** @var \Behat\Mink\Element\NodeElement[] $table_headers */
    $table_headers = $this->getSession()->getPage()->findAll('css', 'th');
    if (!$table_headers) {
      throw new \Exception('There are no table headers to check in.');
    }

    $found_table_header = NULL;
    foreach ($table_headers as $table_header) {
      if ($table_header->getText() === $field) {
        $found_table_header = $table_header;
      }
    }

    if (!$found_table_header) {
      throw new \Exception(sprintf('Translation field element %s not found', $field));
    }

    $table = $found_table_header->getParent()->getParent()->getParent();

    return $table->findField('Translation');
  }

}
