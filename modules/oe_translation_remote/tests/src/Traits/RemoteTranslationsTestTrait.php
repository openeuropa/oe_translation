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
      if ($expected_info['review']) {
        $this->assertTrue($cols[2]->hasLink('Review'), sprintf('The %s language has a Review link', $language->label()));
      }
      else {
        $this->assertFalse($cols[2]->hasLink('Review'), sprintf('The %s language does not have a Review link', $language->label()));
      }
    }
  }

}
