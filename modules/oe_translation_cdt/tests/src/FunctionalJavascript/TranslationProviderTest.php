<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_cdt\FunctionalJavascript;

use Drupal\Core\Session\AccountInterface;
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
   * The user running the tests.
   */
  protected UserInterface $user;

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

    $this->user = $this->setUpTranslatorUser();
    $this->drupalLogin($this->user);
  }

  /**
   * Tests the CDT translation provider configuration form.
   */
  public function testCdtProviderConfiguration(): void {
    $user = $this->drupalCreateUser([
      'administer remote translators',
      'access administration pages',
      'access toolbar',
    ]);
    assert($user instanceof AccountInterface);
    $this->drupalLogin($user);

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
    $storage = \Drupal::entityTypeManager()->getStorage('remote_translation_provider');
    $storage->resetCache();
    $translator = $storage->load('cdt_provider');
    assert($translator instanceof RemoteTranslatorProviderInterface);
    $this->assertEquals('CDT provider', $translator->label());
    $this->assertEquals('cdt', $translator->getProviderPlugin());
    $default_language_mapping = [];
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
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
    $translator = \Drupal::entityTypeManager()->getStorage('remote_translation_provider')->load('cdt_provider');
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
    // Set PT language mapping.
    $translator = RemoteTranslatorProvider::load('cdt');
    assert($translator instanceof RemoteTranslatorProviderInterface);
    $configuration = $translator->getProviderConfiguration() ?? [];
    $configuration['language_mapping']['pt-pt'] = 'PT';
    $translator->setProviderConfiguration($configuration);
    $translator->save();

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
    $this->getSession()->getPage()->fillField('translator_configuration[cdt][contact_usernames][0][value]', 'TESTUSER');
    $this->getSession()->getPage()->fillField('translator_configuration[cdt][deliver_to][0][value]', 'TESTUSER');
    $this->getSession()->getPage()->fillField('Department', '123');
    $this->getSession()->getPage()->fillField('Phone number', '123456');
    $this->getSession()->getPage()->fillField('Priority', 'NO');

    // Assert the languages' validation.
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('Please select at least one language.');

    // Select 2 languages.
    $this->getSession()->getPage()->checkField('Bulgarian');
    $this->getSession()->getPage()->checkField('Portuguese');
    $this->getSession()->getPage()->pressButton('Save and send');

    $this->assertSession()->pageTextContains('The translation request has been sent to CDT.');

    // Assert that we got back a correct response and our request was correctly
    // updated.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $this->assertCount(1, $requests);
    $request = reset($requests);
    $this->assertInstanceOf(TranslationRequestCdtInterface::class, $request);
    $this->assertEquals('en', $request->getSourceLanguageCode());
    $target_languages = $request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('bg', 'Requested'), $target_languages['bg']);
    $this->assertEquals(new LanguageWithStatus('pt-pt', 'Requested'), $target_languages['pt-pt']);
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED, $request->getRequestStatus());
    $this->assertEquals('cdt', $request->getTranslatorProvider()->id());

    // Assert the log messages.
    $expected_logs = [
      1 => [
        'Info',
        'The translation request was successfully validated.',
        $this->user->label(),
      ],
      2 => [
        'Info',
        "The translation request was successfully sent to CDT with correlation ID: {$request->getCorrelationId()}.",
        $this->user->label(),
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

}
