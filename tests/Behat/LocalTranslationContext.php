<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Behat;

use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Behat\Hook\Scope\BeforeFeatureScope;
use Behat\Mink\Element\NodeElement;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use DrupalTest\BehatTraits\Traits\BrowserCapabilityDetectionTrait;
use PHPUnit\Framework\Assert;

/**
 * Context specific to TMGMT-based local translation.
 */
class LocalTranslationContext extends RawDrupalContext {

  use BrowserCapabilityDetectionTrait;

  /**
   * Installs the test module.
   *
   * @param \Behat\Behat\Hook\Scope\BeforeFeatureScope $scope
   *   The scope.
   *
   * @BeforeFeature @translation
   */
  public static function installTestModule(BeforeFeatureScope $scope): void {
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
  public static function uninstallTestModule(AfterFeatureScope $scope): void {
    \Drupal::service('module_installer')->uninstall(['oe_translation_test']);
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
    if (!$element) {
      throw new \Exception(sprintf('The translation element for the field %s was not found.', $field));
    }

    Assert::assertEquals($value, $element->getValue());
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
  public function fillInTranslationFormElement(string $field, string $value): void {
    $element = $this->getTranslationElementForField($field);
    if (!$element) {
      throw new \Exception(sprintf('The translation element for the field %s was not found.', $field));
    }

    // If the element doesn't have a WYSIWYG, we simply fill the field in.
    $wysiwyg = $element->getParent()->find('css', '#cke_' . $element->getAttribute('id'));
    if (!$wysiwyg) {
      $this->getSession()->getPage()->fillField($element->getAttribute('id'), $value);
      return;
    }

    // Otherwise, we add it to the WYSIWYG source if we have JS.
    if ($this->browserSupportsJavaScript()) {
      $button = $wysiwyg->find('xpath', '//a[@title="Source"]');
      $button->click();
      $textarea = $wysiwyg->find('xpath', '//textarea');
      $textarea->setValue($value);
      return;
    }

    // Fallback to non-JS capabilities which would not render the WYSIWYG.
    $this->getSession()->getPage()->fillField($element->getAttribute('id'), $value);
  }

  /**
   * Checks that a translation form element is not there.
   *
   * @param string $field
   *   The field.
   *
   * @Then the translation form element for the :field field is not present
   */
  public function translationElementForFieldNotExists(string $field): void {
    $element = $this->getTranslationElementForField($field);
    if ($element) {
      throw new \Exception(sprintf('The translation element for the field %s was found and it should not have been.', $field));
    }
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
    $table_header = $this->getSession()->getPage()->find('xpath', "//table//th[contains(text(), '" . $field . "')]");
    if (!$table_header) {
      return NULL;
    }

    $table = $table_header->getParent()->getParent()->getParent();

    return $table->findField('Translation');
  }

}
