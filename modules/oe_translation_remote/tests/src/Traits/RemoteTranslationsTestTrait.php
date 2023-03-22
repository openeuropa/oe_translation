<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_remote\Traits;

use Behat\Mink\Element\NodeElement;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;

/**
 * Helpers for testing remote translation providers.
 */
trait RemoteTranslationsTestTrait {

  /**
   * Asserts the ongoing translation languages table.
   *
   * @param array $languages
   *   The expected languages.
   */
  protected function assertRemoteOngoingTranslationLanguages(array $languages): void {
    $languages = array_values($languages);
    $table = $this->getSession()->getPage()->find('css', 'table.remote-translation-languages-table');
    $this->assertCount(count($languages), $table->findAll('css', 'tbody tr'));
    $rows = $table->findAll('css', 'tbody tr');
    foreach ($rows as $key => $row) {
      $cols = $row->findAll('css', 'td');
      $hreflang = $row->getAttribute('hreflang');
      $expected_info = $languages[$key];
      $this->assertEquals($expected_info['langcode'], $hreflang);
      $language = ConfigurableLanguage::load($hreflang);
      $this->assertEquals($language->getName(), $cols[0]->getText());
      $this->assertLanguageStatus($expected_info['status'], $cols[1]);
      $operations_col = 2;
      if (\Drupal::moduleHandler()->moduleExists('oe_translation_epoetry')) {
        $this->assertEquals($expected_info['accepted_deadline'], $cols[2]->getText());
        $operations_col = 3;
      }

      if ($expected_info['review']) {
        $this->assertTrue($cols[$operations_col]->hasLink('Review'), sprintf('The %s language has a Review link', $language->label()));
      }
      else {
        $this->assertFalse($cols[$operations_col]->hasLink('Review'), sprintf('The %s language does not have a Review link', $language->label()));
      }
    }
  }

  /**
   * Asserts the ongoing translations table.
   *
   * @param array $translations
   *   The expected translations.
   */
  protected function assertOngoingTranslations(array $translations): void {
    $table = $this->getSession()->getPage()->find('css', 'table.ongoing-remote-translation-requests-table');
    $this->assertCount(count($translations), $table->findAll('css', 'tbody tr'));
    $rows = $table->findAll('css', 'tbody tr');
    foreach ($rows as $key => $row) {
      $cols = $row->findAll('css', 'td');
      $expected_info = $translations[$key];
      $this->assertEquals($expected_info['translator'], $cols[0]->getText());
      $this->assertRequestStatus($expected_info['status'], $cols[1]);
      $this->assertEquals($expected_info['title_url'], $cols[2]->findLink($expected_info['title'])->getAttribute('href'));
      $this->assertEquals($expected_info['revision'], $cols[3]->getText());
      $this->assertEquals($expected_info['is_default'], $cols[4]->getText());
      $this->assertTrue($cols[5]->hasLink('View'));
    }
  }

  /**
   * Asserts the language status value and tooltip text.
   *
   * @param string $expected_status
   *   The expected status.
   * @param \Behat\Mink\Element\NodeElement $column
   *   The entire column value.
   */
  protected function assertLanguageStatus(string $expected_status, NodeElement $column): void {
    $text = $column->getText();
    if (strpos($text, 'ⓘ') !== FALSE) {
      $text = trim(str_replace('ⓘ', '', $text));
    }

    $this->assertEquals($expected_status, $text);
    switch ($expected_status) {
      case TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED:
        $this->assertEquals('The language has been requested.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW:
        $this->assertEquals('The translation for this language has arrived and is ready for review.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestRemoteInterface::STATUS_LANGUAGE_ACCEPTED:
        $this->assertEquals('The translation for this language has been internally accepted and is ready for synchronisation.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestRemoteInterface::STATUS_LANGUAGE_SYNCHRONISED:
        $this->assertEquals('The translation for this language has been synchronised.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;
    }

    throw new \Exception(sprintf('The %s status tooltip is not covered by an assertion', $expected_status));
  }

}
