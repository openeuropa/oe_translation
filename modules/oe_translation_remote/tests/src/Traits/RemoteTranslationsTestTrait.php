<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_remote\Traits;

use Drupal\language\Entity\ConfigurableLanguage;

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
      $this->assertEquals($expected_info['status'], $cols[1]->getText());
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
      $this->assertEquals($expected_info['status'], $cols[1]->getText());
      $this->assertEquals($expected_info['title_url'], $cols[2]->findLink($expected_info['title'])->getAttribute('href'));
      $this->assertEquals($expected_info['revision'], $cols[3]->getText());
      $this->assertEquals($expected_info['is_default'], $cols[4]->getText());
      $this->assertTrue($cols[5]->hasLink('View'));
    }
  }

}
