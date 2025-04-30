<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_etrans\FunctionalJavascript;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\NodeInterface;
use Drupal\oe_translation_etrans\TranslationRequestEtrans;
use Drupal\oe_translation_etrans\TranslationRequestEtransInterface;
use Drupal\oe_translation_etrans_mock\EtransTranslationMockHelper;
use Drupal\Tests\oe_translation\FunctionalJavascript\TranslationTestBase;
use Drupal\Tests\oe_translation\Traits\TranslationsTestTrait;
use Drupal\Tests\oe_translation_remote\Traits\RemoteTranslationsTestTrait;
use Drupal\node\Entity\Node;
use Drupal\oe_translation\LanguageWithStatus;
use Drupal\oe_translation_remote\Entity\RemoteTranslatorProvider;
use Drupal\user\Entity\Role;

/**
 * Tests the remote translations via etrans.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 *
 * @group batch2
 */
class EtransTranslationTest extends TranslationTestBase {

  use TranslationsTestTrait;
  use RemoteTranslationsTestTrait;

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
    'oe_translation_etrans',
    'oe_translation_etrans_mock',
    'oe_translation_local',
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

    $provider = RemoteTranslatorProvider::create([
      'id' => 'etrans',
      'label' => 'eTrans',
      'plugin' => 'etrans',
      'plugin_configuration' => [
        'language_mapping' => [],
        'domain' => 'GEN',
      ],
      'enabled' => TRUE,
    ]);
    $provider->save();

    FieldConfig::create([
      'field_name' => 'is_etranslation',
      'entity_type' => 'node',
      'bundle' => 'oe_demo_translatable_page',
    ])->save();

    $this->user = $this->setUpTranslatorUser();
    $role = Role::load('oe_translator');
    $role->save();
    $this->drupalLogin($this->user);
  }

  /**
   * Tests the etrans translation provider configuration form.
   */
  public function testEtransProviderConfiguration(): void {
    $user = $this->drupalCreateUser([
      'administer remote translators',
      'access administration pages',
      'access toolbar',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/structure/remote-translation-provider');
    $this->assertSession()->pageTextContains('Remote Translator Provider entities');
    $this->clickLink('Add Remote Translator Provider');
    $this->getSession()->getPage()->selectFieldOption('Plugin', 'eTrans');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Plugin configuration for eTrans');

    $this->getSession()->getPage()->fillField('Name', 'Etrans provider');
    $this->getSession()->getPage()->find('css', '.admin-link .link')->press();
    $this->assertSession()->waitForField('Machine-readable name');
    $this->getSession()->getPage()->fillField('Machine-readable name', 'etrans_provider');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Created the Etrans provider Remote Translator Provider.');

    // Make sure the configuration is saved properly.
    $storage = \Drupal::entityTypeManager()->getStorage('remote_translation_provider');
    $storage->resetCache();
    $translator = $storage->load('etrans_provider');
    $this->assertEquals('Etrans provider', $translator->label());
    $this->assertEquals('etrans', $translator->getProviderPlugin());
    $default_language_mapping = [];
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $default_language_mapping[$language->getId()] = strtoupper($language->getId());
    }
    $this->assertEquals([
      'language_mapping' => $default_language_mapping,
      'domain' => 'GEN',
    ], $translator->getProviderConfiguration());
  }

  /**
   * Tests the main remote translation flow using Etrans.
   *
   * This is similar to RemoteTranslationTest::testSingleTranslationFlow but
   * simplified and focusing more on the Etrans specific aspects.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function testEtransSingleTranslationFlow(): void {
    // Set PT language mapping.
    $translator = RemoteTranslatorProvider::load('etrans');
    $configuration = $translator->getProviderConfiguration();
    $configuration['language_mapping']['pt-pt'] = 'PT';
    $translator->setProviderConfiguration($configuration);
    $translator->save();

    $node = $this->createBasicTestNode('oe_demo_translatable_page', "The translation's page");
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');

    $select = $this->assertSession()->selectExists('Translator');
    // The Etrans translator is preselected because it's the only one.
    $this->assertEquals('etrans', $select->find('css', 'option[selected]')->getValue());
    $this->assertSession()->pageTextContains('New translation request using eTrans');

    // Assert the languages' validation.
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('Please select at least one language.');

    // Select 2 languages.
    $this->getSession()->getPage()->checkField('Bulgarian');
    $this->getSession()->getPage()->checkField('Portuguese');

    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The etrans request has been sent to DGT.');

    $translation_requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $this->assertCount(1, $translation_requests);
    /** @var \Drupal\oe_translation_etrans\TranslationRequestEtransInterface $translation_request */
    $translation_request = reset($translation_requests);

    // Assert that we send the correct information.
    $requests = \Drupal::state()->get('oe_translation_etrans_mock.mock_requests', []);
    $this->assertCount(1, $requests);
    $request = reset($requests);
    $this->assertEquals(sprintf('{"callerInformation":{"externalReference":"%s"},"documentToTranslate":{"document":{"content":"CjwhRE9DVFlQRSBodG1sIFBVQkxJQyAiLS8vVzNDLy9EVEQgWEhUTUwgMS4wIFN0cmljdC8vRU4iICJodHRwOi8vd3d3LnczLm9yZy9UUi94aHRtbDEvRFREL3hodG1sMS1zdHJpY3QuZHRkIj4KPGh0bWwgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGh0bWwiPgogIDxoZWFkPgogICAgPG1ldGEgaHR0cC1lcXVpdj0iY29udGVudC10eXBlIiBjb250ZW50PSJ0ZXh0L2h0bWw7IGNoYXJzZXQ9dXRmLTgiIC8+CiAgICA8bWV0YSBuYW1lPSJyZXF1ZXN0SWQiIGNvbnRlbnQ9IjEiIC8+CiAgICA8bWV0YSBuYW1lPSJsYW5ndWFnZVNvdXJjZSIgY29udGVudD0iRU4iIC8+CiAgICA8dGl0bGU+UmVxdWVzdCBJRCAxPC90aXRsZT4KICA8L2hlYWQ+CiAgPGJvZHk+CiAgICAgICAgICA8ZGl2IGNsYXNzPSJhc3NldCIgaWQ9Iml0ZW0tMSI+CiAgICAgICAgICAgICAgICAgIDwhLS0KICAgICAgICAgIGxhYmVsPSJUaXRsZSIKICAgICAgICAgIGNvbnRleHQ9IlsxXVt0aXRsZV1bMF1bdmFsdWVdIgogICAgICAgICAgLS0+CiAgICAgICAgICA8ZGl2IGNsYXNzPSJhdG9tIiBpZD0iYk1WMWJkR2wwYkdWZFd6QmRXM1poYkhWbCI+VGhlIHRyYW5zbGF0aW9uJ3MgcGFnZTwvZGl2PgogICAgICAgICAgICAgIDwvZGl2PgogICAgICA8L2JvZHk+CjwvaHRtbD4K","format":"html"}},"outputFormat":"html","preserveTags":true,"sourceLanguage":"EN","targetLanguages":["BG","PT"],"domain":"GEN","deliveries":{"http":"http:\/\/web:8080\/build\/en\/oe-translation\/etrans\/delivery"},"notifications":{"failure":{"http":"http:\/\/web:8080\/build\/en\/oe-translation\/etrans\/error"}}}', $translation_request->getSavedAccessToken()), $request);

    // Assert that we got back a correct response and our request was correctly
    // updated.
    $this->assertInstanceOf(TranslationRequestEtransInterface::class, $translation_request);
    $this->assertEquals('en', $translation_request->getSourceLanguageCode());
    $target_languages = $translation_request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('bg', 'Requested'), $target_languages['bg']);
    $this->assertEquals(new LanguageWithStatus('pt-pt', 'Requested'), $target_languages['pt-pt']);
    $this->assertEquals('etrans', $translation_request->getTranslatorProvider()->id());
    $this->assertEquals('GEN', $translation_request->getTranslatorProvider()->getProviderConfiguration()['domain']);
    $this->assertEquals('55555', $translation_request->getRemoteId());

    // Assert the request status table.
    $this->assertRequestStatusTable([
      'Requested',
      'eTrans',
      '55555',
    ]);

    // We have the table of languages of this last request, all active but
    // none yet translated.
    $expected_languages = [];
    foreach (['bg', 'pt-pt'] as $langcode) {
      $expected_languages[$langcode] = [
        'langcode' => $langcode,
        'status' => 'Requested',
        'review' => FALSE,
      ];
    }
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Send the translation.
    \Drupal::service('oe_translation_test.logger.mock_logger')->clearLogs();
    EtransTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EtransTranslationMockHelper::translateRequest($translation_request, 'pt-pt');
    $logs = \Drupal::service('oe_translation_test.logger.mock_logger')->getLogs();
    // We have 1 log item with incoming delivery.
    $this->assertCount(1, $logs);
    $log = reset($logs);
    $this->assertEquals('The etrans for request ID: <strong>@request_id</strong> in @language has been received: @etrans', $log['message']);

    // Assert the Queue items.
    $queue_items = \Drupal::database()->select('queue')
      ->condition('name', 'oe_translation_etrans_delivery')
      ->fields('queue')
      ->execute()->fetchAll();
    $this->assertCount(1, $queue_items);
    $item = reset($queue_items);
    $data = unserialize($item->data);
    $this->assertEquals($translation_request->id(), $data['translation_request_id']);
    $this->assertEquals('pt-pt', $data['language']);
    $this->assertEquals("The translation's page - PT", $data['data']['title'][0]['value']['#translation']['#text']);

    // Run the queue to process the PT translation.
    $this->runQueue();

    $this->getSession()->reload();
    $expected_languages['pt-pt']['status'] = 'Review';
    $expected_languages['pt-pt']['review'] = TRUE;
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Assert the logs on the page.
    $expected_logs = [];
    $expected_logs[1] = [
      'Info',
      'The Portuguese translation has been delivered.',
      $this->user->label(),
    ];
    $this->assertLogMessagesTable($expected_logs);

    // Sync the translation (first accept it).
    $this->getSession()->getPage()->clickLink('Review');
    $this->getSession()->getPage()->pressButton('Save and accept');
    $this->drupalGet($translation_request->toUrl());
    $expected_logs[2] = [
      'Info',
      'The Portuguese translation has been accepted.',
      $this->user->label(),
    ];
    $this->assertLogMessagesTable($expected_logs);
    $this->getSession()->getPage()->clickLink('Review');
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('The translation in Portuguese has been synchronised.');
    $this->assertSession()->addressEquals('/en/translation-request/' . $translation_request->id());
    $expected_languages['pt-pt']['status'] = 'Synchronised';
    $expected_languages['pt-pt']['review'] = FALSE;
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);
    $expected_logs[3] = [
      'Info',
      'The Portuguese translation has been synchronised with the content.',
      $this->user->label(),
    ];
    $this->assertLogMessagesTable($expected_logs);

    $node = Node::load($node->id());
    $this->assertTrue($node->hasTranslation('pt-pt'));
    $this->assertFalse((bool) $node->get('is_etranslation')->value);
    $this->assertEquals("The translation's page - PT", $node->getTranslation('pt-pt')->label());
    $translation = $node->getTranslation('pt-pt');
    $this->assertTrue((bool) $translation->get('is_etranslation')->value);

    // Create a local translation in IT and assert it doesn't set the etrans
    // flag.
    $this->drupalGet($node->toUrl());
    $this->clickLink('Translate');
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="it"] a')->click();
    $element = $this->getSession()->getPage()->find('xpath', "//textarea[contains(@name,'[translation]')]");
    $element->setValue("The translation's page IT");
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('The translation has been saved.');
    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    $node = Node::load($node->id());
    $this->assertFalse((bool) $node->get('is_etranslation')->value);
    $this->assertTrue((bool) $node->getTranslation('pt-pt')->get('is_etranslation')->value);
    $this->assertFalse((bool) $node->getTranslation('it')->get('is_etranslation')->value);
  }

  /**
   * Tests the delivery errors.
   */
  public function testDeliveryErrors(): void {
    $node = $this->createBasicTestNode();
    $request = $this->createNodeTranslationRequest($node, '55555');
    $request->save();

    \Drupal::service('oe_translation_test.logger.mock_logger')->clearLogs();

    // Send a delivery error request.
    EtransTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EtransTranslationMockHelper::sendErrorCallback($request, 'fr', '4000', 'There was an error with the request.');
    $logs = \Drupal::service('oe_translation_test.logger.mock_logger')->getLogs();
    $log = reset($logs);
    $message = 'Etrans sent a failure notification for the Request ID: <strong>55555</strong> with the following error code 4000 and error message: There was an error with the request..';
    $this->assertEquals($message, (string) new FormattableMarkup($log['message'], $log['context']));

    // The error is also on the translation request.
    \Drupal::entityTypeManager()->getStorage('oe_translation_request')->resetCache();
    /** @var \Drupal\oe_translation\Entity\TranslationRequestInterface $request */
    $request = \Drupal::entityTypeManager()->getStorage('oe_translation_request')->load($request->id());
    $expected_logs = [];
    $expected_logs[1] = [
      'Error',
      'Etrans sent a failure notification for the Request ID: 55555 with the following error code 4000 and error message: There was an error with the request..',
      'Anonymous',
    ];
    $this->assertLogMessagesValues($request, $expected_logs);
    $this->assertEquals(TranslationRequestEtransInterface::STATUS_REQUEST_FAILED, $request->getRequestStatus());

    // Go to mark it as Failed and Finished.
    $this->drupalLogin($this->user);
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->pageTextNotContains('There are no ongoing remote translation requests');
    $expected_ongoing = [
      'translator' => 'eTrans',
      'status' => 'Failed',
      'title' => 'Basic translation node',
      'title_url' => $node->toUrl()->toString(),
      'revision' => $node->getRevisionId(),
      'is_default' => 'Yes',
    ];

    $this->assertOngoingTranslations([$expected_ongoing]);
    $this->getSession()->getPage()->pressButton('List additional actions');
    $this->clickLink('Mark as finished');
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations');
    $this->assertSession()->pageTextContains('There are no ongoing remote translation requests');
    \Drupal::entityTypeManager()->getStorage('oe_translation_request')->resetCache();
    /** @var \Drupal\oe_translation\Entity\TranslationRequestInterface $request */
    $request = \Drupal::entityTypeManager()->getStorage('oe_translation_request')->load($request->id());
    $this->assertEquals(TranslationRequestEtransInterface::STATUS_REQUEST_FAILED_FINISHED, $request->getRequestStatus());

    // Send a delivery for a missing translation request.
    $missing_request = $this->createNodeTranslationRequest($node, '44444');
    \Drupal::service('oe_translation_test.logger.mock_logger')->clearLogs();
    EtransTranslationMockHelper::translateRequest($missing_request, 'fr');
    $logs = \Drupal::service('oe_translation_test.logger.mock_logger')->getLogs();
    $log = reset($logs);
    $this->assertEquals('An etrans delivery was attempted for the request ID 44444 but for which there was no translation request.', $log['message']);

    // Send a delivery for a translation request, but with an incorrect token.
    \Drupal::service('oe_translation_test.logger.mock_logger')->clearLogs();
    $request->set('etrans_access_token', 'wrong-token');
    EtransTranslationMockHelper::translateRequest($request, 'fr');
    $logs = \Drupal::service('oe_translation_test.logger.mock_logger')->getLogs();
    $log = reset($logs);
    $this->assertEquals('An etrans delivery was attempted for the request ID 55555 but for which there was no translation request.', $log['message']);

    // Assert we get a 404 if we make a request without the request ID or token.
    foreach (['delivery', 'error'] as $endpoint) {
      $data = [
        'requestId' => 'ID',
      ];
      EtransTranslationMockHelper::performNotification($data, $endpoint);
      $this->assertEquals('404', EtransTranslationMockHelper::$httpCode);

      $data = [
        'externalReference' => 'token',
      ];
      EtransTranslationMockHelper::performNotification($data, $endpoint);
      $this->assertEquals('404', EtransTranslationMockHelper::$httpCode);
    }
  }

  /**
   * Tests that the available languages for ePoetry can be altered.
   */
  public function testLanguagesAlter(): void {
    $node = $this->createBasicTestNode();
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->assertSession()->pageTextContains('New translation request using eTrans');

    $this->assertSession()->fieldExists('Bulgarian');
    $this->assertSession()->fieldExists('French');

    \Drupal::state()->set('oe_translation_test.remove_languages', ['bg']);

    $this->getSession()->reload();
    $this->assertSession()->pageTextContains('New translation request using eTrans');

    $this->assertSession()->fieldNotExists('Bulgarian');
    $this->assertSession()->fieldExists('French');
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
  protected function assertLogMessagesValues(TranslationRequestEtransInterface $request, array $logs): void {
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
   * Runs the etrans queue.
   */
  protected function runQueue() {
    $queue = \Drupal::service('queue')->get('oe_translation_etrans_delivery');
    $queue_worker = \Drupal::service('plugin.manager.queue_worker')->createInstance('oe_translation_etrans_delivery');

    while ($item = $queue->claimItem()) {
      try {
        $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
      }
      catch (\Exception $e) {
        $queue->releaseItem($item);
      }
    }
  }

  /**
   * Creates a translation request for a given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $remote_id
   *   The remote ID.
   * @param string $status
   *   The request status.
   * @param array $languages
   *   The language data (status + langcode.)
   *
   * @return \Drupal\oe_translation_etrans\TranslationRequestEtransInterface
   *   The request.
   */
  protected function createNodeTranslationRequest(NodeInterface $node, string $remote_id, string $status = TranslationRequestEtransInterface::STATUS_REQUEST_REQUESTED, array $languages = []): TranslationRequestEtransInterface {
    if (!$languages) {
      $languages[] = [
        'status' => TranslationRequestEtransInterface::STATUS_LANGUAGE_REQUESTED,
        'langcode' => 'fr',
      ];
    }

    $request = TranslationRequestEtrans::create([
      'bundle' => 'etrans',
      'source_language_code' => $node->language()->getId(),
      'target_languages' => $languages,
      'translator_provider' => 'etrans',
    ]);

    $request->setContentEntity($node);
    $data = \Drupal::service('oe_translation.translation_source_manager')->extractData($node->getUntranslated());
    $request->setData($data);
    $request->setRequestStatus($status);
    $request->setRemoteId($remote_id);
    $request->generateAccessToken();

    return $request;
  }

}
