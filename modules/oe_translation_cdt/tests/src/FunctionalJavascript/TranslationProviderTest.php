<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_cdt\FunctionalJavascript;

use Drupal\oe_translation\LanguageWithStatus;
use Drupal\oe_translation_cdt\TranslationRequestCdtInterface;
use Drupal\oe_translation_remote\Entity\RemoteTranslatorProvider;
use Drupal\oe_translation_remote\Entity\RemoteTranslatorProviderInterface;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Drupal\Tests\oe_translation\FunctionalJavascript\TranslationTestBase;
use Drupal\Tests\oe_translation\Traits\TranslationsTestTrait;
use Drupal\Tests\oe_translation_remote\Traits\RemoteTranslationsTestTrait;
use Drupal\user\UserInterface;

/**
 * Tests the remote translations via CDT.
 *
 * @group batch2
 */
class TranslationProviderTest extends TranslationTestBase {

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
    'oe_translation_cdt',
    'oe_translation_cdt_mock',
  ];

  /**
   * The remote translations manager.
   */
  protected UserInterface $remoteTranslationManager;

  /**
   * The translator user.
   */
  protected UserInterface $translatorUser;

  /**
   * The authenticated user.
   */
  protected UserInterface $authenticatedUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $provider = RemoteTranslatorProvider::load('cdt');
    assert($provider instanceof RemoteTranslatorProvider, 'The CDT provider should be enabled.');
    $configuration = $provider->getProviderConfiguration() ?? [];
    $configuration['language_mapping'] = [];
    $provider->set('enabled', TRUE);
    $provider->setProviderConfiguration($configuration);
    $provider->save();

    $this->authenticatedUser = $this->drupalCreateUser();
    $this->translatorUser = $this->setUpTranslatorUser();
    $this->remoteTranslationManager = $this->drupalCreateUser([
      'administer remote translators',
      'access administration pages',
      'access toolbar',
    ]);
  }

  /**
   * Tests the CDT translation provider configuration form.
   */
  public function testCdtProviderConfiguration(): void {
    $this->drupalLogin($this->remoteTranslationManager);

    $this->drupalGet('admin/structure/remote-translation-provider');
    $this->assertSession()->pageTextContains('Remote Translator Provider entities');
    $this->clickLink('Add Remote Translator Provider');
    $this->getSession()->getPage()->selectFieldOption('Plugin', 'CDT');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Plugin configuration for CDT');

    // Create a new CDT provider.
    $this->getSession()->getPage()->fillField('Name', 'CDT provider');
    $this->getSession()->getPage()->find('css', '.admin-link .link')->press();
    $this->assertSession()->waitForField('Machine-readable name');
    $this->getSession()->getPage()->fillField('Machine-readable name', 'cdt_provider');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Created the CDT provider Remote Translator Provider.');

    // Make sure the configuration is saved properly.
    $provider_storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('remote_translation_provider');
    $provider_storage->resetCache();
    $translator = $provider_storage->load('cdt_provider');
    assert($translator instanceof RemoteTranslatorProviderInterface);

    $this->assertEquals('CDT provider', $translator->label());
    $this->assertEquals('cdt', $translator->getProviderPlugin());
    $default_language_mapping = [];
    $languages = $this->container->get('language_manager')->getLanguages();
    foreach ($languages as $language) {
      $default_language_mapping[$language->getId()] = strtoupper($language->getId());
    }
    $this->assertEquals([
      'language_mapping' => $default_language_mapping,
    ], $translator->getProviderConfiguration());

    // Edit the provider.
    $this->drupalGet($translator->toUrl('edit-form'));
    $this->assertSession()->fieldValueEquals('Name', 'CDT provider');
    $this->getSession()->getPage()->fillField('Name', 'CDT provider edited');
    $this->assertSession()->fieldValueEquals('German', 'DE');
    $this->getSession()->getPage()->fillField('German', 'TEST');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Saved the CDT provider edited Remote Translator Provider.');
    $translator = $provider_storage->load('cdt_provider');
    assert($translator instanceof RemoteTranslatorProviderInterface);
    $this->assertEquals([
      'language_mapping' => ['de' => 'TEST'] + $default_language_mapping,
    ], $translator->getProviderConfiguration() ?? []);
  }

  /**
   * Tests the main remote translation flow using CDT.
   *
   * This is similar to RemoteTranslationTest::testSingleTranslationFlow but
   * simplified and focusing more on the CDT specific aspects.
   */
  public function testCdtSingleTranslationFlow(): void {
    $this->drupalLogin($this->translatorUser);

    // Set PT language mapping.
    $translator = RemoteTranslatorProvider::load('cdt');
    assert($translator instanceof RemoteTranslatorProviderInterface);
    $configuration = $translator->getProviderConfiguration() ?? [];
    $configuration['language_mapping']['pt-pt'] = 'PT';
    $translator->setProviderConfiguration($configuration);
    $translator->save();

    $request = $this->createTestTranslation([
      'bg' => 'Bulgarian',
      'pt-pt' => 'Portuguese',
    ]);

    // Request the permanent ID.
    $this->assertSession()->linkNotExists('Refresh status', 'The Refresh status link should not be available yet.');
    $this->clickLink('Get Permanent ID');
    $this->assertSession()->pageTextContains('The permanent ID has been updated.');
    $request = $this->getUncachedTranslationRequest($request);
    $cdt_id = $request->getCdtId();
    $this->assertNotEmpty($cdt_id, 'The CDT ID should be set.');
    $this->assertEquals($cdt_id, sprintf('%s/%s', date('Y'), $request->getCorrelationId()));

    // Refresh the status, nothing should change.
    $this->clickLink('Refresh status');
    $this->assertSession()->pageTextContains('The request status did not change.');

    // Mock the completion of the BG language. Index 0 belongs to the request.
    $this->clickLink('Update status to Completed (mock)', 1);
    $request = $this->getUncachedTranslationRequest($request);
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW, $request->getTargetLanguages()['bg']->getStatus());
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED, $request->getTargetLanguages()['pt-pt']->getStatus());
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED, $request->getRequestStatus());

    // Fetch the BG translation.
    $this->clickLink('Fetch the translation');

    // Accept the BG translation.
    $this->clickLink('Review');
    $this->assertSession()->pageTextContains('BG translation of');
    $this->getSession()->getPage()->pressButton('Save and accept');
    $this->assertSession()->pageTextContains('Accepted');
    $request = $this->getUncachedTranslationRequest($request);
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_ACCEPTED, $request->getTargetLanguages()['bg']->getStatus());
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED, $request->getTargetLanguages()['pt-pt']->getStatus());
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED, $request->getRequestStatus());

    // Synchronise the BG translation.
    $this->clickLink('Review');
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('Synchronised');
    $request = $this->getUncachedTranslationRequest($request);
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_SYNCHRONISED, $request->getTargetLanguages()['bg']->getStatus());
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED, $request->getTargetLanguages()['pt-pt']->getStatus());
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED, $request->getRequestStatus());

    // Finalize the PT translation.
    $this->clickLink('Update status to Completed (mock)', 2);
    $request = $this->getUncachedTranslationRequest($request);
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW, $request->getTargetLanguages()['pt-pt']->getStatus());
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED, $request->getRequestStatus());
    $this->clickLink('Fetch the translation');
    $this->clickLink('Review');
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $request = $this->getUncachedTranslationRequest($request);
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_SYNCHRONISED, $request->getTargetLanguages()['pt-pt']->getStatus());
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED, $request->getRequestStatus());

    // Assert the log messages.
    $this->drupalGet('translation-request/' . $request->id());
    $expected_logs = [
      1 => [
        'Info',
        'The translation request was successfully validated.',
        $this->translatorUser->label(),
      ],
      2 => [
        'Info',
        "The translation request was successfully sent to CDT with correlation ID: {$request->getCorrelationId()}.",
        $this->translatorUser->label(),
      ],
      3 => [
        'Info',
        "Manually updated the permanent ID.Updated cdt_id field to $cdt_id.",
        $this->translatorUser->label(),
      ],
      4 => [
        'Info',
        'Received CDT callback, updating the job...The following languages are updated: bg (Requested =&gt; Review).',
        $this->translatorUser->label(),
      ],
      5 => [
        'Info',
        'The Bulgarian translation has been accepted.',
        $this->translatorUser->label(),
      ],
      6 => [
        'Info',
        'The Bulgarian translation has been synchronised with the content.',
        $this->translatorUser->label(),
      ],
      7 => [
        'Info',
        'Received CDT callback, updating the job...The following languages are updated: pt-pt (Requested =&gt; Review).',
        $this->translatorUser->label(),
      ],
      8 => [
        'Info',
        'The Portuguese translation has been synchronised with the content.',
        $this->translatorUser->label(),
      ],
    ];
    $this->assertLogMessagesTable($expected_logs);

    // Check the translation dashboard.
    $this->drupalGet('admin/content/cdt-translation-requests');
    $this->assertSession()->pageTextNotContains('Access denied');
    $this->assertSession()->pageTextContains($cdt_id);
    $this->assertSession()->pageTextContains('Finished');

    // Check the access to the translation pages.
    $this->drupalLogin($this->authenticatedUser);
    $this->drupalGet('admin/content/cdt-translation-requests');
    $this->assertSession()->pageTextContains('Access denied');
    $this->drupalGet($request->toUrl('canonical'));
    $this->assertSession()->pageTextContains('Access denied');
    $this->drupalLogout();
    $this->drupalGet('admin/content/cdt-translation-requests');
    $this->assertSession()->pageTextContains('Access denied');
    $this->drupalGet($request->toUrl('canonical'));
    $this->assertSession()->pageTextContains('Access denied');
  }

  /**
   * Tests the cancelled remote translation flow using CDT.
   */
  public function testCancelledTranslationFlow(): void {
    $this->drupalLogin($this->translatorUser);
    $request = $this->createTestTranslation([
      'de' => 'German',
    ]);

    // Request the permanent ID.
    $this->clickLink('Get Permanent ID');

    // Mock the cancellation of the DE language. Index 0 belongs to the request.
    $this->getSession()->getPage()->find('css', 'tr[hreflang="de"]')->pressButton('List additional actions');
    $this->clickLink('Update status to Cancelled (mock)', 1);
    $request = $this->getUncachedTranslationRequest($request);
    $this->assertEquals(TranslationRequestCdtInterface::STATUS_LANGUAGE_CANCELLED, $request->getTargetLanguages()['de']->getStatus());
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED, $request->getRequestStatus());

    // Assert the log messages.
    $this->drupalGet('translation-request/' . $request->id());
    $expected_logs = [
      1 => [
        'Info',
        'The translation request was successfully validated.',
        $this->translatorUser->label(),
      ],
      2 => [
        'Info',
        "The translation request was successfully sent to CDT with correlation ID: {$request->getCorrelationId()}.",
        $this->translatorUser->label(),
      ],
      3 => [
        'Info',
        "Manually updated the permanent ID.Updated cdt_id field to {$request->getCdtId()}.",
        $this->translatorUser->label(),
      ],
      4 => [
        'Info',
        'Received CDT callback, updating the job...The following languages are updated: de (Requested =&gt; Cancelled).',
        $this->translatorUser->label(),
      ],
    ];
    $this->assertLogMessagesTable($expected_logs);
  }

  /**
   * Tests the partially cancelled remote translation flow using CDT.
   */
  public function testPartiallyCancelledTranslationFlow(): void {
    $this->drupalLogin($this->translatorUser);
    $request = $this->createTestTranslation([
      'fr' => 'French',
      'sk' => 'Slovak',
    ]);

    // Request the permanent ID.
    $this->clickLink('Get Permanent ID');

    // Mock the cancellation of the FR language. Index 0 belongs to the request.
    $this->getSession()->getPage()->find('css', 'tr[hreflang="fr"]')->pressButton('List additional actions');
    $this->clickLink('Update status to Cancelled (mock)', 1);
    $request = $this->getUncachedTranslationRequest($request);
    $this->assertEquals(TranslationRequestCdtInterface::STATUS_LANGUAGE_CANCELLED, $request->getTargetLanguages()['fr']->getStatus());
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED, $request->getTargetLanguages()['sk']->getStatus());
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED, $request->getRequestStatus());

    // Mock the acceptance of the SK language.
    $this->clickLink('Update status to Completed (mock)', 2);
    $request = $this->getUncachedTranslationRequest($request);
    $this->assertEquals(TranslationRequestCdtInterface::STATUS_LANGUAGE_CANCELLED, $request->getTargetLanguages()['fr']->getStatus());
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW, $request->getTargetLanguages()['sk']->getStatus());
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED, $request->getRequestStatus());
    $this->clickLink('Fetch the translation');
    $this->clickLink('Review');
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $request = $this->getUncachedTranslationRequest($request);
    $this->assertEquals(TranslationRequestCdtInterface::STATUS_LANGUAGE_CANCELLED, $request->getTargetLanguages()['fr']->getStatus());
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_SYNCHRONISED, $request->getTargetLanguages()['sk']->getStatus());
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED, $request->getRequestStatus());

    // Assert the log messages.
    $this->drupalGet('translation-request/' . $request->id());
    $expected_logs = [
      1 => [
        'Info',
        'The translation request was successfully validated.',
        $this->translatorUser->label(),
      ],
      2 => [
        'Info',
        "The translation request was successfully sent to CDT with correlation ID: {$request->getCorrelationId()}.",
        $this->translatorUser->label(),
      ],
      3 => [
        'Info',
        "Manually updated the permanent ID.Updated cdt_id field to {$request->getCdtId()}.",
        $this->translatorUser->label(),
      ],
      4 => [
        'Info',
        'Received CDT callback, updating the job...The following languages are updated: fr (Requested =&gt; Cancelled).',
        $this->translatorUser->label(),
      ],
      5 => [
        'Info',
        'Received CDT callback, updating the job...The following languages are updated: sk (Requested =&gt; Review).',
        $this->translatorUser->label(),
      ],
      6 => [
        'Info',
        'The Slovak translation has been synchronised with the content.',
        $this->translatorUser->label(),
      ],
    ];
    $this->assertLogMessagesTable($expected_logs);
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
   * Gets the latest translation request for a node.
   *
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $request
   *   The translation request.
   *
   * @return \Drupal\oe_translation_cdt\TranslationRequestCdtInterface
   *   The reloaded translation request.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getUncachedTranslationRequest(TranslationRequestCdtInterface $request): TranslationRequestCdtInterface {
    $controller = $this->entityTypeManager->getStorage($request->getEntityTypeId());
    $controller->resetCache([$request->id()]);
    $refreshed_request = $controller->load($request->id());
    $this->assertInstanceOf(TranslationRequestCdtInterface::class, $refreshed_request);
    return $refreshed_request;
  }

  /**
   * Creates a test translation with specified languages.
   *
   * @param array $requested_languages
   *   The requested languages keyed by the language code and the label.
   *
   * @return \Drupal\oe_translation_cdt\TranslationRequestCdtInterface
   *   The created translation request.
   */
  protected function createTestTranslation(array $requested_languages): TranslationRequestCdtInterface {
    $node = $this->createBasicTestNode('oe_demo_translatable_page', "The translation's page");
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');

    $select = $this->assertSession()->selectExists('Translator');
    // The CDT translator is preselected because it's the only one.
    $this->assertEquals('cdt', $select->find('css', 'option[selected]')->getValue());
    $this->assertSession()->pageTextContains('New translation request using CDT');

    // Select the translation settings.
    $this->getSession()->getPage()->fillField('Comments', 'Test Translation');
    $this->getSession()->getPage()->fillField('Confidentiality', 'NO');
    $this->getSession()->getPage()->fillField('translator_configuration[cdt][contact_usernames][0][value]', 'TESTUSER1');
    $this->getSession()->getPage()->fillField('translator_configuration[cdt][deliver_to][0][value]', 'TESTUSER1');
    $this->getSession()->getPage()->fillField('Department', '123');
    $this->getSession()->getPage()->fillField('Phone number', '123456');
    $this->getSession()->getPage()->fillField('Priority', 'NO');

    // Assert the languages' validation.
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('Please select at least one language.');

    foreach ($requested_languages as $label) {
      $this->getSession()->getPage()->checkField($label);
    }
    $this->getSession()->getPage()->pressButton('Save and send');

    $this->assertSession()->pageTextContains('The translation request has been sent to CDT.');
    $this->assertSession()->pageTextContains('No new translation request can be made because there is already an active translation request for this entity version.');

    // Assert that we got back a correct response and our request was updated.
    $provider_manager = $this->container->get('plugin.manager.oe_translation_remote.remote_translation_provider_manager');
    $requests = $provider_manager->getExistingTranslationRequests($node, TRUE);
    $request = end($requests);
    $this->assertEquals('en', $request->getSourceLanguageCode());
    $target_languages = $request->getTargetLanguages();
    foreach ($requested_languages as $langcode => $label) {
      $this->assertEquals(new LanguageWithStatus($langcode, TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED), $target_languages[$langcode]);
    }
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED, $request->getRequestStatus());
    $this->assertEquals('cdt', $request->getTranslatorProvider()->id());

    return $request;
  }

}