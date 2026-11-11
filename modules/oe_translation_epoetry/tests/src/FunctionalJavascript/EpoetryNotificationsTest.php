<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_epoetry\FunctionalJavascript;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\oe_translation\Entity\TranslationRequest;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\oe_translation_epoetry_mock\EpoetryTranslationMockHelper;
use Drupal\oe_translation_remote\Entity\RemoteTranslatorProvider;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;

/**
 * Tests the ePoetry notification handling and automated request updates.
 *
 * @group batch4
 */
class EpoetryNotificationsTest extends EpoetryTranslationTestBase {

  /**
   * Tests the request notifications subscriber.
   */
  public function testRequestNotifications(): void {
    // Create a node and its active translation request.
    $storage = \Drupal::entityTypeManager()->getStorage('oe_translation_request');
    $node = $this->createBasicTestNode();
    $request = $this->createNodeTranslationRequest($node);
    $request->save();
    $this->assertEquals('Requested', $request->getRequestStatus());
    $this->assertEquals('SenttoDGT', $request->getEpoetryRequestStatus());

    $statuses = [
      'Accepted' => 'Requested',
      'Rejected' => 'Finished',
      'Cancelled' => 'Finished',
      'Executed' => 'Requested',
      'Suspended' => 'Requested',
    ];

    // Keep track of all the log messages because we are sending notifications
    // to the same request entity.
    $expected_logs = [];
    $i = 1;
    foreach ($statuses as $epoetry_status => $request_status) {
      EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
      $notification = [
        'type' => 'RequestStatusChange',
        'status' => $epoetry_status,
      ];
      if ($epoetry_status == 'Rejected') {
        $notification['message'] = 'We are sorry';
      }
      EpoetryTranslationMockHelper::notifyRequest($request, $notification);

      $storage->resetCache();
      /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
      $request = $storage->load($request->id());
      $this->assertEquals($request_status, $request->getRequestStatus(), sprintf('The request status for %s is correct', $epoetry_status));
      $this->assertEquals($epoetry_status, $request->getEpoetryRequestStatus());

      $log_type = in_array($epoetry_status, ['Accepted', 'Executed']) ? 'Info' : 'Warning';
      $log_message = sprintf('The request has been %s by ePoetry. Planning agent: test. Planning sector: DGT. Message: The request status has been changed to %s.', $epoetry_status, $epoetry_status);
      if ($epoetry_status == 'Rejected') {
        $log_message = sprintf('The request has been %s by ePoetry. Planning agent: test. Planning sector: DGT. Message: We are sorry.', $epoetry_status);
      }

      $expected_logs[$i] = [
        $log_type,
        $log_message,
        'Anonymous',
      ];

      $this->assertLogMessagesValues($request, $expected_logs);

      // Reset the statuses for the next iteration.
      $request->setRequestStatus('Requested');
      $request->setEpoetryRequestStatus('SenttoDGT');
      $request->save();
      $i++;
    }

    // Delete the request and send some notifications to assert we are logging
    // the fact that we cannot find the request.
    $request->delete();
    $notification = [
      'type' => 'RequestStatusChange',
      'status' => 'Accepted',
    ];
    EpoetryTranslationMockHelper::notifyRequest($request, $notification);
    $logs = \Drupal::service('oe_translation_test.logger.mock_logger')->getLogs();
    // The last two logs should show the missing request: the last one is our
    // response to ePoetry and the one before last is us logging that we are
    // missing the request.
    $log = array_pop($logs);
    $this->assertEquals(RfcLogLevel::INFO, $log['level']);
    $log = array_pop($logs);
    $this->assertEquals(RfcLogLevel::ERROR, $log['level']);
    $this->assertEquals('The ePoetry notification could not find a translation request for the reference: <strong>@reference</strong>.', $log['message']);
    $this->assertEquals('DIGIT-' . date('Y') . '-02000(00)-00-TRA', $log['context']['@reference']);
  }

  /**
   * Tests the product notifications subscriber.
   */
  public function testProductNotifications(): void {
    // Create a node and its active translation request.
    $storage = \Drupal::entityTypeManager()->getStorage('oe_translation_request');
    $node = $this->createBasicTestNode();
    $request = $this->createNodeTranslationRequest($node);
    $request->save();
    $this->assertEquals('Requested', $request->getTargetLanguage('fr')->getStatus());

    $statuses = [
      'Requested',
      'Cancelled',
      'Rejected',
      'Accepted',
      'Ongoing',
      'ReadyToBeSent',
      'Sent',
      'Closed',
      'Suspended',
    ];

    // Keep track of all the log messages because we are sending notifications
    // to the same request entity.
    $expected_logs = [];
    $i = 1;
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    foreach ($statuses as $status) {
      $notification = [
        'type' => 'ProductStatusChange',
        'status' => $status,
        'language' => 'fr',
      ];

      EpoetryTranslationMockHelper::notifyRequest($request, $notification);

      $storage->resetCache();
      $request = $storage->load($request->id());
      $this->assertEquals($status, $request->getTargetLanguage('fr')->getStatus());

      $log_type = in_array($status, ['Cancelled', 'Rejected', 'Suspended']) ? 'Warning' : 'Info';
      $log_message = sprintf('The French product status has been updated to %s.', $status);

      $expected_logs[$i] = [
        $log_type,
        $log_message,
        'Anonymous',
      ];

      $this->assertLogMessagesValues($request, $expected_logs);
      $i++;
    }

    // Delete the request and send some notifications to assert we are logging
    // the fact that we cannot find the request.
    $request->delete();
    $notification = [
      'type' => 'ProductStatusChange',
      'status' => 'Accepted',
      'language' => 'fr',
    ];
    EpoetryTranslationMockHelper::notifyRequest($request, $notification);
    $logs = \Drupal::service('oe_translation_test.logger.mock_logger')->getLogs();
    // The last two logs should show the missing request: the last one is our
    // response to ePoetry and the one before last is us logging that we are
    // missing the request.
    $log = array_pop($logs);
    $this->assertEquals(RfcLogLevel::INFO, $log['level']);
    $log = array_pop($logs);
    $this->assertEquals(RfcLogLevel::ERROR, $log['level']);
    $this->assertEquals('The ePoetry notification could not find a translation request for the reference: <strong>@reference</strong>.', $log['message']);
    $this->assertEquals('DIGIT-' . date('Y') . '-02000(00)-00-TRA', $log['context']['@reference']);
    \Drupal::service('oe_translation_test.logger.mock_logger')->clearLogs();
    EpoetryTranslationMockHelper::translateRequest($request, 'fr');
    $logs = \Drupal::service('oe_translation_test.logger.mock_logger')->getLogs();
    $log = array_pop($logs);
    $this->assertEquals(RfcLogLevel::INFO, $log['level']);
    $log = array_pop($logs);
    $this->assertEquals(RfcLogLevel::ERROR, $log['level']);
    $this->assertEquals('The ePoetry notification could not find a translation request for the reference: <strong>@reference</strong>.', $log['message']);
    $this->assertEquals('DIGIT-' . date('Y') . '-02000(00)-00-TRA', $log['context']['@reference']);

  }

  /**
   * Tests the product delivery errors.
   */
  public function testProductDeliveryErrors(): void {
    $node = $this->createBasicTestNode();
    $request = $this->createNodeTranslationRequest($node);
    $request->save();

    // Translate the request with a missing/broken translation file.
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EpoetryTranslationMockHelper::$translationRequestErrors['missing translation'] = TRUE;
    EpoetryTranslationMockHelper::translateRequest($request, 'fr');
    $logs = \Drupal::service('oe_translation_test.logger.mock_logger')->getLogs();
    $log = $logs[3];
    $this->assertStringContainsString('The ePoetry notification did not provide a valid translation. Reference:', $log['message']);
    $log = $logs[4];
    $this->assertStringContainsString('<success>false</success><message>Translation data is missing or is invalid.</message>', $log['context']['response']);

    // Translate the request with the file of a different request.
    $request = TranslationRequest::load($request->id());
    \Drupal::service('oe_translation_test.logger.mock_logger')->clearLogs();
    unset(EpoetryTranslationMockHelper::$translationRequestErrors['missing translation']);
    EpoetryTranslationMockHelper::$translationRequestErrors['wrong request id'] = TRUE;
    EpoetryTranslationMockHelper::translateRequest($request, 'fr');
    $logs = \Drupal::service('oe_translation_test.logger.mock_logger')->getLogs();
    $log = $logs[3];
    $this->assertStringContainsString('The ePoetry notification did not provide a valid translation. The translation request file does not match the translation request. Reference:', $log['message']);
    $log = $logs[4];
    $this->assertStringContainsString('<success>false</success><message>The translation request file does not match the translation request.</message>', $log['context']['response']);
  }

  /**
   * Tests the product notifications subscriber locking mechanism.
   */
  public function testProductNotificationsLock(): void {
    // Create a node and its active translation request.
    $storage = \Drupal::entityTypeManager()->getStorage('oe_translation_request');
    $node = $this->createBasicTestNode();
    $request = $this->createNodeTranslationRequest($node);
    $request->save();
    $this->assertEquals('Requested', $request->getTargetLanguage('fr')->getStatus());

    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    // Instruct our event subscriber to delay the processing of the "Requested"
    // product status change to mimic that it is taking place by the time we
    // run the next one.
    \Drupal::state()->set('oe_translation_epoetry_test_delay_requested', TRUE);

    $notification_one = [
      'type' => 'ProductStatusChange',
      'status' => 'Requested',
      'language' => 'fr',
    ];
    $notification_two = [
      'type' => 'ProductStatusChange',
      'status' => 'Accepted',
      'language' => 'fr',
    ];

    // Notify with the "Requested" status but do not wait for the response.
    // Instead, immediately after, notify with the Accepted. The latter should
    // encounter a lock and wait for the "Requested" update to finish.
    EpoetryTranslationMockHelper::notifyRequest($request, $notification_one, FALSE);
    EpoetryTranslationMockHelper::notifyRequest($request, $notification_two);

    // Wait a bit until the "Requested" update has had a chance to finish
    // before loading the request and asserting.
    sleep(6);
    $logs = \Drupal::service('oe_translation_test.logger.mock_logger')->getLogs();
    $log = $logs[6];
    $this->assertEquals('Lock already acquired: The translation request 1 is already being updated.', $log['message']);

    $storage->resetCache();
    /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
    $request = $storage->load($request->id());
    $this->assertEquals('Accepted', $request->getTargetLanguage('fr')->getStatus());

    // Go through each request status and assert they are also correctly waiting
    // to acquire the lock.
    $request_statuses = [
      'Accepted',
      'Cancelled',
      'Suspended',
      'Executed',
      'Rejected',
    ];

    $request->setEpoetryRequestStatus('SenttoDGT');
    $request->save();
    \Drupal::service('oe_translation_test.logger.mock_logger')->clearLogs();
    foreach ($request_statuses as $request_status) {
      $notification_two = [
        'type' => 'RequestStatusChange',
        'status' => $request_status,
      ];
      EpoetryTranslationMockHelper::notifyRequest($request, $notification_one, FALSE);
      EpoetryTranslationMockHelper::notifyRequest($request, $notification_two);

      sleep(6);

      $logs = \Drupal::service('oe_translation_test.logger.mock_logger')->getLogs();
      $log = $logs[6];
      $this->assertEquals('Lock already acquired: The translation request 1 is already being updated.', $log['message']);
      \Drupal::service('oe_translation_test.logger.mock_logger')->clearLogs();

      $storage->resetCache();
      $request = $storage->load($request->id());
      $this->assertEquals($request_status, $request->getEpoetryRequestStatus());
    }

    // Assert the Lock also is checked when the translation comes in.
    $this->assertEmpty($request->getTranslatedData());
    $request->setEpoetryRequestStatus('SenttoDGT');
    $request->save();
    EpoetryTranslationMockHelper::notifyRequest($request, $notification_one, FALSE);
    EpoetryTranslationMockHelper::translateRequest($request, 'fr');

    sleep(6);

    $logs = \Drupal::service('oe_translation_test.logger.mock_logger')->getLogs();
    $log = $logs[6];
    $this->assertEquals('Lock already acquired: The translation request 1 is already being updated.', $log['message']);
    $storage->resetCache();
    /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
    $request = $storage->load($request->id());
    $this->assertNotNull($request->getTranslatedData()['fr']);
  }

  /**
   * Tests we don't change the language status once the translation has arrived.
   */
  public function testLanguageStatusAfterReview(): void {
    // Create a node and its active translation request.
    $storage = \Drupal::entityTypeManager()->getStorage('oe_translation_request');
    $node = $this->createBasicTestNode();
    $request = $this->createNodeTranslationRequest($node);
    $request->save();
    $this->assertEquals('Requested', $request->getTargetLanguage('fr')->getStatus());

    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;

    // Send the translation.
    EpoetryTranslationMockHelper::translateRequest($request, 'fr');
    $storage->resetCache();
    $request = $storage->load($request->id());
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW, $request->getTargetLanguage('fr')->getStatus());

    // Send a status notification.
    $notification = [
      'type' => 'ProductStatusChange',
      'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SENT,
      'language' => 'fr',
    ];

    EpoetryTranslationMockHelper::notifyRequest($request, $notification);

    $storage->resetCache();
    $request = $storage->load($request->id());
    // The language status did not change.
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW, $request->getTargetLanguage('fr')->getStatus());

    // We do still log that the notification came from ePoetry.
    $expected_logs = [];
    $expected_logs[1] = [
      'Info',
      'The French translation has been delivered.',
      'Anonymous',
    ];
    $expected_logs[2] = [
      'Info',
      'The French product status has been updated to Sent.',
      'Anonymous',
    ];
    $this->assertLogMessagesValues($request, $expected_logs);
  }

  /**
   * Tests that we can configure requests to auto-accept upon delivery.
   */
  public function testAutoAccept(): void {
    // Create a test node.
    $node = $this->createBasicTestNode();

    // Create a request.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->getSession()->getPage()->checkField('Bulgarian');
    $this->getSession()->getPage()->checkField('Auto accept translations');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }

    $this->getSession()->getPage()->fillField('Message', 'Message to the provider');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    // Accept and translate the language.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $request = reset($requests);
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EpoetryTranslationMockHelper::translateRequest($request, 'bg');
    $this->getSession()->reload();

    $expected_languages = [];
    $expected_languages['bg'] = [
      'langcode' => 'bg',
      // The language has been automatically accepted.
      'status' => 'Accepted',
      'accepted_deadline' => 'N/A',
      'review' => TRUE,
    ];
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);
    $this->getSession()->getPage()->find('css', 'summary')->click();
    $this->assertSession()->pageTextContains('The Bulgarian translation has been automatically accepted.');
    $this->assertSession()->pageTextNotContains('The Bulgarian translation has been accepted.');

    // Set the global setting to auto-accept all requests and make a new
    // request for a new node. This time, don't check the auto-accept.
    $provider = RemoteTranslatorProvider::load('epoetry');
    $configuration = $provider->getProviderConfiguration();
    $configuration['auto_accept'] = TRUE;
    $provider->setProviderConfiguration($configuration);
    $provider->save();

    // Create a test node.
    $node = $this->createBasicTestNode();

    // Create a request.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->getSession()->getPage()->checkField('French');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }

    $this->getSession()->getPage()->fillField('Message', 'Message to the provider');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    // Translate the language.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $request = reset($requests);
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EpoetryTranslationMockHelper::translateRequest($request, 'fr');
    $this->getSession()->reload();

    $expected_languages = [];
    $expected_languages['fr'] = [
      'langcode' => 'fr',
     // The language has been automatically accepted.
      'status' => 'Accepted',
      'accepted_deadline' => 'N/A',
      'review' => TRUE,
    ];
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);
    $this->getSession()->getPage()->find('css', 'summary')->click();
    $this->assertSession()->pageTextContains('The French translation has been automatically accepted.');
    $this->assertSession()->pageTextNotContains('The French translation has been accepted.');

    // While the provider is set to auto-accept requests, make another request
    // and override the option.
    // Create a test node.
    $node = $this->createBasicTestNode();

    // Create a request.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->getSession()->getPage()->checkField('Spanish');
    // Unset the auto-accept.
    $this->getSession()->getPage()->uncheckField('Auto accept translations');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }

    $this->getSession()->getPage()->fillField('Message', 'Message to the provider');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    // Translate the language.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $request = reset($requests);
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EpoetryTranslationMockHelper::translateRequest($request, 'es');
    $this->getSession()->reload();

    $expected_languages = [];
    $expected_languages['es'] = [
      'langcode' => 'es',
      // The translation should not be automatically accepted, so the status
      // is in review.
      'status' => 'Review',
      'accepted_deadline' => 'N/A',
      'review' => TRUE,
    ];
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);
    $this->getSession()->getPage()->find('css', 'summary')->click();
    $this->assertSession()->pageTextContains('The Spanish translation has been delivered.');
    $this->assertSession()->pageTextNotContains('The Spanish translation has been automatically accepted.');
  }

  /**
   * Tests that we can configure requests to auto-sync upon delivery.
   */
  public function testAutoSync(): void {
    // Create a test node.
    $node = $this->createBasicTestNode();

    // Create a request.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->getSession()->getPage()->checkField('Bulgarian');
    $this->getSession()->getPage()->checkField('Auto sync translations');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/11/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }

    $this->getSession()->getPage()->fillField('Message', 'Message to the provider');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');
    // Assert there is no weekend deadline warning.
    $this->assertSession()->pageTextNotContains('The selected deadline is a weekend day and it can delay receiving the translation request.');

    // Translate the language.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $request = reset($requests);
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EpoetryTranslationMockHelper::translateRequest($request, 'bg');
    $this->drupalGet($request->toUrl());

    $expected_languages = [];
    $expected_languages['bg'] = [
      'langcode' => 'bg',
      'status' => 'Synchronised',
      'accepted_deadline' => 'N/A',
      'review' => FALSE,
    ];
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);
    $this->getSession()->getPage()->find('css', 'summary')->click();
    $this->assertSession()->pageTextContains('The Bulgarian translation has been automatically synchronised with the content.');
    $this->assertSession()->pageTextNotContains('The Bulgarian translation has been synchronised with the content.');

    // Set the global setting to auto-sync all requests and make a new
    // request for a new node. This time, don't check the auto-sync.
    $provider = RemoteTranslatorProvider::load('epoetry');
    $configuration = $provider->getProviderConfiguration();
    $configuration['auto_sync'] = TRUE;
    $provider->setProviderConfiguration($configuration);
    $provider->save();

    // Create a test node.
    $node = $this->createBasicTestNode();

    // Create a request.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->getSession()->getPage()->checkField('French');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/11/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }

    $this->getSession()->getPage()->fillField('Message', 'Message to the provider');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');
    // Assert there is no weekend deadline warning.
    $this->assertSession()->pageTextNotContains('The selected deadline is a weekend day and it can delay receiving the translation request.');

    // Translate the language.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $request = reset($requests);
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EpoetryTranslationMockHelper::translateRequest($request, 'fr');
    $this->drupalGet($request->toUrl());

    $expected_languages = [];
    $expected_languages['fr'] = [
      'langcode' => 'fr',
      // The language has been automatically synced.
      'status' => 'Synchronised',
      'accepted_deadline' => 'N/A',
      'review' => FALSE,
    ];
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);
    $this->getSession()->getPage()->find('css', 'summary')->click();
    $this->assertSession()->pageTextContains('The French translation has been automatically synchronised with the content.');
    $this->assertSession()->pageTextNotContains('The French translation has been synchronised with the content.');

    // While the global auto-sync option is enabled, make another request and
    // override the option.
    // Create a test node.
    $node = $this->createBasicTestNode();

    // Create a request.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->getSession()->getPage()->checkField('Spanish');
    // When the auto-sync is globally enabled, both auto-sync and auto-accept
    // are enabled in the request, so unset both options.
    $this->getSession()->getPage()->uncheckField('Auto accept translations');
    $this->getSession()->getPage()->uncheckField('Auto sync translations');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/11/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }

    $this->getSession()->getPage()->fillField('Message', 'Message to the provider');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');
    // Assert there is no weekend deadline warning.
    $this->assertSession()->pageTextNotContains('The selected deadline is a weekend day and it can delay receiving the translation request.');

    // Translate the language.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $request = reset($requests);
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EpoetryTranslationMockHelper::translateRequest($request, 'es');
    $this->drupalGet($request->toUrl());

    $expected_languages = [];
    $expected_languages['es'] = [
      'langcode' => 'es',
      // The translation should not be automatically accepted, so the status
      // is in review.
      'status' => 'Review',
      'accepted_deadline' => 'N/A',
      'review' => TRUE,
    ];
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);
    $this->getSession()->getPage()->find('css', 'summary')->click();
    $this->assertSession()->pageTextContains('The Spanish translation has been delivered.');
    $this->assertSession()->pageTextNotContains('The Spanish translation has been automatically synchronised with the content.');
  }

  /**
   * Tests that we can configure the notification validation to kick in.
   */
  public function testNotificationTicketValidation(): void {
    // Create a node and its active translation request.
    $node = $this->createBasicTestNode();
    $request = $this->createNodeTranslationRequest($node);
    $request->save();
    $this->assertEquals('Requested', $request->getRequestStatus());
    $this->assertEquals('SenttoDGT', $request->getEpoetryRequestStatus());

    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    $notification = [
      'type' => 'RequestStatusChange',
      'status' => 'Accepted',
    ];
    EpoetryTranslationMockHelper::notifyRequest($request, $notification);
    $logs = \Drupal::service('oe_translation_test.logger.mock_logger')->getLogs();
    foreach ($logs as $log) {
      $this->assertStringNotContainsString('The mock ticket validation kicked in.', $log['message']);
    }

    \Drupal::service('oe_translation_test.logger.mock_logger')->clearLogs();

    // Turn on the ticket validation.
    $this->writeSettings([
      'settings' => [
        'epoetry.ticket_validation.on' =>
        (object) [
          'value' => 1,
          'required' => TRUE,
        ],
      ],
    ]
    );

    EpoetryTranslationMockHelper::notifyRequest($request, $notification);
    $logs = \Drupal::service('oe_translation_test.logger.mock_logger')->getLogs();
    $found = FALSE;
    foreach ($logs as $log) {
      if (str_contains('The mock ticket validation kicked in.', $log['message'])) {
        $found = TRUE;
      }
    }

    $this->assertEquals($found, 'The mock ticket validation kicked in');
  }

  /**
   * Tests various cases of a request status being updated automatically.
   */
  public function testAutomatedRequestStatusUpdate(): void {
    $cases = [
      // Test that requests which have all the languages Cancelled, Rejected or
      // in Review, get marked as Translated.
      TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED,
      // Test that requests which have all the languages Cancelled, Rejected or
      // Synchronised, get marked as Finished.
      TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED,
    ];

    $storage = \Drupal::entityTypeManager()->getStorage('oe_translation_request');
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;

    $part = 0;
    foreach ($cases as $expected_status) {
      $languages = [];
      $node = $this->createBasicTestNode();
      $languages[] = [
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REQUESTED,
        'langcode' => 'fr',
      ];
      $languages[] = [
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REQUESTED,
        'langcode' => 'bg',
      ];
      $languages[] = [
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REQUESTED,
        'langcode' => 'it',
      ];
      $languages[] = [
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REQUESTED,
        'langcode' => 'de',
      ];
      $request = $this->createNodeTranslationRequest($node, TranslationRequestEpoetryInterface::STATUS_REQUEST_REQUESTED, $languages);
      $request->setRequestId(
        [
          'code' => 'DIGIT',
          'year' => date('Y'),
          'number' => 2000,
          'part' => $part,
          'version' => 0,
          'service' => 'TRA',
        ]
      );
      $request->save();

      // Execute the request.
      $notification = [
        'type' => 'RequestStatusChange',
        'status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_EXECUTED,
      ];
      EpoetryTranslationMockHelper::notifyRequest($request, $notification);
      $storage->resetCache();
      /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
      $request = $storage->load($request->id());
      // No change in the request status.
      $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED, $request->getRequestStatus());
      $this->assertEquals(TranslationRequestEpoetryInterface::STATUS_REQUEST_EXECUTED, $request->getEpoetryRequestStatus());
      // Translate the Italian (this works for both cases).
      EpoetryTranslationMockHelper::translateRequest($request, 'it');
      $storage->resetCache();
      /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
      $request = $storage->load($request->id());
      // No change in the request status.
      $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED, $request->getRequestStatus());
      if ($expected_status === TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED) {
        // If we expect the request to be Finished, also Sync the translation.
        \Drupal::service('oe_translation_remote.translation_synchroniser')->synchronise($request, 'it');
        $storage->resetCache();
        /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
        $request = $storage->load($request->id());
        // No change in the request status.
        $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED, $request->getRequestStatus());
      }
      // Cancel/reject the languages one by one and assert that when the last
      // one got cancelled/rejected, the request status gets marked as Finished.
      $notification = [
        'type' => 'ProductStatusChange',
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_CANCELLED,
        'language' => 'fr',
      ];
      EpoetryTranslationMockHelper::notifyRequest($request, $notification);
      $storage->resetCache();
      /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
      $request = $storage->load($request->id());
      // No change in the request status.
      $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED, $request->getRequestStatus());
      // Cancel the Bulgarian.
      $notification = [
        'type' => 'ProductStatusChange',
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_CANCELLED,
        'language' => 'bg',
      ];
      EpoetryTranslationMockHelper::notifyRequest($request, $notification);
      $storage->resetCache();
      /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
      $request = $storage->load($request->id());
      // No change in the request status.
      $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED, $request->getRequestStatus());
      // Reject the German.
      $notification = [
        'type' => 'ProductStatusChange',
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REJECTED,
        'language' => 'de',
      ];
      EpoetryTranslationMockHelper::notifyRequest($request, $notification);
      $storage->resetCache();
      /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
      $request = $storage->load($request->id());
      // The request status has been marked as Translated if one of the
      // languages is in Review or Finished if one of the languages is synced.
      $this->assertEquals($expected_status, $request->getRequestStatus());

      if ($expected_status === TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED) {
        // If the request was marked as Finished, resend the synced translation
        // and assert the request was marked back to Translated.
        EpoetryTranslationMockHelper::translateRequest($request, 'it');
        $storage->resetCache();
        /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
        $request = $storage->load($request->id());
        $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED, $request->getRequestStatus());
      }
      $part++;
    }
  }

}
