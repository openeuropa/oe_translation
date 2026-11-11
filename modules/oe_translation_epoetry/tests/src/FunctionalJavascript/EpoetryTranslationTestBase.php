<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_epoetry\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Tests\oe_translation\FunctionalJavascript\TranslationTestBase;
use Drupal\Tests\oe_translation\Traits\TranslationsTestTrait;
use Drupal\Tests\oe_translation_epoetry\EpoetryTranslationTestTrait;
use Drupal\Tests\oe_translation_remote\Traits\RemoteTranslationsTestTrait;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\oe_translation_remote\Entity\RemoteTranslatorProvider;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Drupal\user\Entity\Role;

/**
 * Base class for the ePoetry translation tests.
 */
abstract class EpoetryTranslationTestBase extends TranslationTestBase {

  use TranslationsTestTrait;
  use RemoteTranslationsTestTrait;
  use EpoetryTranslationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'address',
    'paragraphs',
    'entity_reference_revisions',
    'menu_link_content',
    'views',
    'oe_translation',
    'oe_translation_test',
    'oe_translation_remote',
    'oe_translation_epoetry',
    'oe_translation_epoetry_test',
    'oe_translation_epoetry_mock',
  ];

  /**
   * The user running the tests.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $provider = RemoteTranslatorProvider::load('epoetry');
    $configuration = $provider->getProviderConfiguration();
    $configuration['title_prefix'] = 'A title prefix';
    $configuration['site_id'] = 'A site ID';
    $configuration['auto_accept'] = FALSE;
    $configuration['language_mapping'] = [];
    $provider->set('enabled', TRUE);
    $provider->setProviderConfiguration($configuration);
    $provider->save();

    $this->user = $this->setUpTranslatorUser();
    $role = Role::load('oe_translator');
    $role->grantPermission('request epoetry translation');
    // From Drupal 11.4 the username is only rendered as a link to the user
    // profile if the current user has "access user profiles" permission.
    $role->grantPermission('access user profiles');
    $role->save();
    $this->drupalLogin($this->user);
  }

  /**
   * Asserts the log messages table output.
   *
   * @param array $logs
   *   The log information array keyed by the number with type and message.
   */
  protected function assertLogMessagesTable(array $logs): void {
    $table = $this->getSession()->getPage()->find('css', 'table.translation-request-log-messages');
    $rows = $table->findAll('css', 'tbody tr');
    $this->assertCount(count($logs), $rows);
    $actual = [];
    foreach ($rows as $row) {
      $cols = $row->findAll('css', 'td');
      $actual[(int) $cols[0]->getHtml()] = [
        $cols[1]->getHtml(),
        strip_tags($cols[2]->getHtml()),
        strip_tags($cols[3]->getHtml()),
      ];
    }

    $this->assertEquals($logs, $actual);
  }

  /**
   * Asserts the logs messages in the entity.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The request.
   * @param array $logs
   *   The expected logs.
   */
  protected function assertLogMessagesValues(TranslationRequestEpoetryInterface $request, array $logs): void {
    $actual = [];
    $i = 1;
    foreach ($request->getLogMessages() as $log) {
      $actual[$i] = [
        ucfirst($log->getType()),
        strip_tags((string) $log->getMessage()),
        $log->getOwner()->label(),
      ];

      $i++;
    }

    $this->assertEquals($logs, $actual);
  }

  /**
   * Asserts the request status table.
   *
   * @param array $columns
   *   The expected columns.
   */
  protected function assertRequestStatusTable(array $columns): void {
    $table = $this->getSession()->getPage()->find('css', 'table.request-status-meta-table');
    $this->assertCount(count($columns), $table->findAll('css', 'tbody tr td'));
    $table_columns = $table->findAll('css', 'tbody td');
    foreach ($columns as $delta => $column) {
      if ($delta === 0) {
        // Assert the request status and tooltip.
        $this->assertRequestStatus($column, $table_columns[$delta]);
        continue;
      }

      if ($delta === 2) {
        // Assert the ePoetry request status and tooltip.
        $this->assertEpoetryRequestStatus($column, $table_columns[$delta]);
        continue;
      }

      $this->assertEquals($column, $table_columns[$delta]->getText());
    }
  }

  /**
   * Asserts the request status value and tooltip text.
   *
   * @param string $expected_status
   *   The expected status.
   * @param \Behat\Mink\Element\NodeElement $column
   *   The entire column value.
   */
  protected function assertEpoetryRequestStatus(string $expected_status, NodeElement $column): void {
    $text = $column->getText();
    if (str_contains($text, 'ⓘ')) {
      $text = trim(str_replace('ⓘ', '', $text));
    }

    $this->assertEquals($expected_status, $text);
    if ($expected_status === '') {
      // It means the request doesn't have an ePoetry status.
      return;
    }
    switch ($expected_status) {
      case TranslationRequestEpoetryInterface::STATUS_REQUEST_SENT:
        $this->assertEquals('The request has been sent to ePoetry and they need to accept or reject it.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED:
        $this->assertEquals('The translation request has been accepted by ePoetry.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestEpoetryInterface::STATUS_REQUEST_REJECTED:
        $this->assertEquals('The translation request has been rejected by ePoetry. Please check the request logs for the reason why it was rejected.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestEpoetryInterface::STATUS_REQUEST_CANCELLED:
        $this->assertEquals('The translation request has been cancelled by ePoetry. You cannot reopen this request but you can make a new one.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestEpoetryInterface::STATUS_REQUEST_SUSPENDED:
        $this->assertEquals('The translation request has been suspended by ePoetry. This can be a temporary measure and the request can be unsuspended by ePoetry.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestEpoetryInterface::STATUS_REQUEST_EXECUTED:
        $this->assertEquals('The translation request has been executed by ePoetry. This means they have dispatched the translations for all the languages.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;
    }

    throw new \Exception(sprintf('The %s status tooltip is not covered by any assertion', $expected_status));
  }

  /**
   * Asserts the language status value and tooltip text.
   *
   * @param string $expected_status
   *   The expected status.
   * @param \Behat\Mink\Element\NodeElement $column
   *   The entire column value.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  protected function assertLanguageStatus(string $expected_status, NodeElement $column): void {
    $text = $column->getText();
    if (str_contains($text, 'ⓘ')) {
      $text = trim(str_replace('ⓘ', '', $text));
    }

    $this->assertEquals($expected_status, $text);
    switch ($expected_status) {
      case TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW:
        $this->assertEquals('The translation for this language has arrived and is ready for review.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestRemoteInterface::STATUS_LANGUAGE_ACCEPTED:
        $this->assertEquals('The translation for this language has been internally accepted and is ready for synchronisation.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestRemoteInterface::STATUS_LANGUAGE_SYNCHRONISED:
        $this->assertEquals('The translation for this language has been synchronised.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REQUESTED . ' [in ePoetry]':
        $this->assertEquals('The language has been requested.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestEpoetryInterface::STATUS_LANGUAGE_ACCEPTED . ' [in ePoetry]':
        $this->assertEquals('The translation in this language has been accepted in ePoetry.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestEpoetryInterface::STATUS_LANGUAGE_ONGOING . ' [in ePoetry]':
        $this->assertEquals('The content is being translated in ePoetry.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestEpoetryInterface::STATUS_LANGUAGE_READY . ' [in ePoetry]':
        $this->assertEquals('The translation is ready and will be shortly sent by ePoetry.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SENT . ' [in ePoetry]':
        $this->assertEquals('The translation has been sent by ePoetry.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestEpoetryInterface::STATUS_LANGUAGE_CLOSED . ' [in ePoetry]':
        $this->assertEquals('The translation has been sent and the task has been closed by ePoetry.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestEpoetryInterface::STATUS_LANGUAGE_CANCELLED . ' [in ePoetry]':
        $this->assertEquals('The translation for this language has been cancelled by ePoetry. It cannot be reopened.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REJECTED . ' [in ePoetry]':
        $this->assertEquals('The translation for this language has been rejected by ePoetry. It cannot be reopened.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SUSPENDED . ' [in ePoetry]':
        $this->assertEquals('The translation for this language has been suspended by ePoetry. This can be a temporary measure and it can be unsuspended by ePoetry.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;
    }

    throw new \Exception(sprintf('The %s status tooltip is not covered by any assertion', $expected_status));
  }

}
