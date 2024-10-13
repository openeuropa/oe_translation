<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_remote\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Config\FileStorage;
use Drupal\Tests\oe_translation\FunctionalJavascript\TranslationTestBase;
use Drupal\Tests\oe_translation\Traits\TranslationsTestTrait;
use Drupal\Tests\oe_translation_remote\Traits\RemoteTranslationsTestTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\oe_translation\LanguageWithStatus;
use Drupal\oe_translation_remote_test\TestRemoteTranslationMockHelper;
use Drupal\oe_translation_remote_test\TranslationRequestTestRemote;
use Drupal\user\Entity\Role;

/**
 * Tests the remote translations.
 *
 * @group batch1
 */
class RemoteTranslationTest extends TranslationTestBase {

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
    'oe_translation',
    'oe_translation_test',
    'oe_translation_remote',
    'oe_translation_remote_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    \Drupal::service('content_translation.manager')
      ->setEnabled('paragraph', 'demo_paragraph_type', TRUE);
    \Drupal::service('content_translation.manager')
      ->setEnabled('paragraph', 'demo_inner_paragraph_type', TRUE);
    $this->container->get('router.builder')->rebuild();

    // Import the configs from the tasks folder.
    $storage = new FileStorage(\Drupal::service('extension.list.module')->getPath('oe_translation_test') . '/config/tasks');
    foreach ($storage->listAll() as $name) {
      $this->importConfigFromFile($name, $storage);
    }

    // Mark the test entity reference field as embeddable to behave as
    // composite entities.
    $this->config('oe_translation.settings')
      ->set('translation_source_embedded_fields', [
        'node' => [
          'ott_content_reference' => TRUE,
        ],
      ])
      ->save();

    // Limit the address field value translatability.
    $field = FieldConfig::load('node.oe_demo_translatable_page.ott_address');
    $field->setThirdPartySetting('content_translation', 'translation_sync', [
      'langcode' => '0',
      'administrative_area' => '0',
      'locality' => '0',
      'dependent_locality' => '0',
      'sorting_code' => '0',
      'address_line1' => '0',
      'address_line2' => '0',
      'organization' => '0',
      'given_name' => 'given_name',
      'additional_name' => '0',
      'family_name' => 'family_name',
      'country_code' => '0',
      'postal_code' => '0',
    ]);
    $field->save();

    $user = $this->setUpTranslatorUser();
    $this->drupalLogin($user);
  }

  /**
   * Test the remote translation provider configuration.
   */
  public function testRemoteTranslationProviderForm(): void {
    $user = $this->drupalCreateUser([
      'administer remote translators',
      'access administration pages',
      'access toolbar',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/structure/remote-translation-provider');
    $this->assertSession()->pageTextContains('Remote Translator Provider entities');
    $this->assertSession()->linkExists('Add Remote Translator Provider');
    $this->clickLink('Add Remote Translator Provider');

    // Assert form elements.
    $this->assertSession()->pageTextContains('Add remote translator provider');
    $this->assertSession()->fieldExists('Name');
    $this->assertSession()->pageTextContains('Name of the Remote translation provider.');
    $this->assertSession()->elementAttributeContains('css', 'input#edit-label', 'required', 'required');
    $select = $this->assertSession()->selectExists('Plugin');
    // Assert that all the installed plugins are available in the select.
    $options = [];
    foreach ($select->findAll('xpath', '//option') as $element) {
      $options[$element->getValue()] = trim($element->getText());
    }
    $expected_options = [
      'Please select a plugin',
      'Remote one',
      'Remote two',
    ];
    $this->assertEqualsCanonicalizing($expected_options, $options);
    $this->assertSession()->pageTextContains('The plugin to be used with this translator.');
    $this->assertSession()->elementAttributeContains('css', 'select#edit-plugin', 'required', 'required');

    // Assert different configuration form based on selected plugin.
    $this->getSession()->getPage()->selectFieldOption('Plugin', 'Remote one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Plugin configuration for Remote one');
    $this->assertSession()->pageTextContains('This plugin does not have any configuration options.');
    $this->getSession()->getPage()->selectFieldOption('Plugin', 'Remote two');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Plugin configuration for Remote two');
    $this->assertSession()->pageTextNotContains('Plugin configuration for Remote one');
    $this->assertSession()->pageTextNotContains('This plugin does not have any configuration options.');
    $this->assertSession()->fieldExists('A test configuration string');
    $this->assertSession()->checkboxChecked('Enabled');

    // Create a new remote translator provider.
    $this->getSession()->getPage()->fillField('Name', 'Test provider');
    $this->assertSession()->waitForText('Machine name');
    $this->getSession()->getPage()->fillField('A test configuration string', 'Plugin configuration.');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Created the Test provider Remote Translator Provider.');

    // Make sure the configuration is saved properly.
    $remote_translator = \Drupal::entityTypeManager()->getStorage('remote_translation_provider')->load('test_provider');
    $this->assertEquals('Test provider', $remote_translator->label());
    $this->assertEquals('remote_two', $remote_translator->getProviderPlugin());
    $this->assertEquals('remote_two', $remote_translator->isEnabled());
    $this->assertEquals(['configuration_string' => 'Plugin configuration.'], $remote_translator->getProviderConfiguration());

    // Assert we can see the Delete link on the edit form because we don't
    // yet have any translation requests with that provider.
    $this->drupalGet($remote_translator->toUrl('edit-form'));
    $this->assertSession()->linkExistsExact('Delete');

    // Create a translation request with the remote_two provider to test we
    // can no longer delete the provider.
    $request = TranslationRequestTestRemote::create([
      'translator_provider' => 'test_provider',
    ]);
    $request->save();
    $this->getSession()->reload();
    $this->assertSession()->linkNotExistsExact('Delete');
    $request->delete();

    // Edit the provider.
    $this->drupalGet($remote_translator->toUrl('edit-form'));
    $this->assertSession()->fieldValueEquals('Name', 'Test provider');
    $this->assertSession()->fieldValueEquals('A test configuration string', 'Plugin configuration.');
    $this->getSession()->getPage()->fillField('Name', 'Test provider edited');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Saved the Test provider edited Remote Translator Provider.');

    // Disable the provider and assert the form checkbox.
    $this->drupalGet($remote_translator->toUrl('edit-form'));
    $this->getSession()->getPage()->uncheckField('Enabled');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Saved the Test provider edited Remote Translator Provider.');
    $this->drupalGet($remote_translator->toUrl('edit-form'));
    $this->assertSession()->checkboxNotChecked('Enabled');

    // Delete the provider.
    $this->drupalGet($remote_translator->toUrl('delete-form'));
    $this->getSession()->getPage()->pressButton('Delete');
    $this->assertSession()->pageTextContains('The remote translator provider Test provider edited has been deleted.');

    // Assert that we can see the Remote translation page on a node translation
    // page because we have the two remote translators.
    $user = $this->setUpTranslatorUser();
    $this->drupalLogin($user);
    $node = $this->createBasicTestNode();
    $this->drupalGet('/node/' . $node->id() . '/translations');
    $this->assertSession()->linkExistsExact('Remote translations');
    $this->clickLink('Remote translations');

    // Assert we have the form for selecting the translator.
    $select = $this->assertSession()->selectExists('Translator');
    $options = $select->findAll('css', 'option');
    array_walk($options, function (NodeElement &$option) {
      $option = $option->getValue();
    });
    $this->assertEquals([
      '',
      'remote_one',
      'remote_two',
    ], $options);

    // Delete a remote translators and disable one, and assert we no longer
    // have a Remote translations page.
    /** @var \Drupal\oe_translation_remote\Entity\RemoteTranslatorProviderInterface[] $translators */
    $translators = $this->entityTypeManager->getStorage('remote_translation_provider')->loadMultiple();
    $translator = array_pop($translators);
    $translator->delete();
    $translator = array_pop($translators);
    $translator->set('enabled', FALSE);
    $translator->save();

    $this->drupalGet('/node/' . $node->id() . '/translations');
    $this->assertSession()->linkNotExistsExact('Remote translations');
    $this->drupalGet('/node/' . $node->id() . '/translations/remote');
    $this->assertSession()->pageTextContains('Access denied');
  }

  /**
   * Tests the main remote translation flow.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function testSingleTranslationFlow(): void {
    // Create a node and perform the translation flow.
    $node = $this->createFullTestNode();
    $this->drupalGet($node->toUrl());

    $request_storage = \Drupal::entityTypeManager()->getStorage('oe_translation_request');
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');

    $this->clickLink('Translate');
    // Assert we don't have yet any translations.
    $this->assertSession()->pageTextContains('Ongoing remote translation requests');
    $this->assertSession()->pageTextContains('There are no ongoing remote translation requests');

    $this->clickLink('Remote translations');

    // Assert we have the form for selecting the translator.
    $select = $this->assertSession()->selectExists('Translator');
    $options = $select->findAll('css', 'option');
    array_walk($options, function (NodeElement &$option) {
      $option = $option->getValue();
    });
    $this->assertEquals([
      '',
      'remote_one',
      'remote_two',
    ], $options);

    // Select the Remote one translator, pick languages and "send the request".
    $select->selectOption('remote_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('New translation request using Remote one');

    // Assert the languages validation.
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('Please select at least one language.');

    // Select 2 languages.
    $this->getSession()->getPage()->checkField('Bulgarian');
    $this->getSession()->getPage()->checkField('French');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent');
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations/remote');
    $this->assertSession()->elementTextEquals('css', 'form h3', 'Ongoing remote translation request via Remote one.');
    // We can no longer make a new translation.
    $this->assertSession()->pageTextContains('No new translation request can be made because there is already an active translation request for this entity version.');
    $this->assertSession()->fieldDisabled('Translator');

    // Assert the request status table.
    $this->assertRequestStatusTable([
      'Requested',
      'Remote one',
    ]);

    // We have the table of languages of this last request, all active but
    // none yet translated.
    $expected_languages = [];
    foreach (['bg', 'fr'] as $langcode) {
      $expected_languages[$langcode] = [
        'langcode' => $langcode,
        'status' => 'Requested',
        'review' => FALSE,
      ];
    }
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $this->assertCount(1, $requests);
    $request = reset($requests);
    $this->assertInstanceOf(TranslationRequestTestRemote::class, $request);
    $this->assertEquals('en', $request->getSourceLanguageCode());
    $target_languages = $request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('bg', 'Requested'), $target_languages['bg']);
    $this->assertEquals(new LanguageWithStatus('fr', 'Requested'), $target_languages['fr']);
    $this->assertEquals('Requested', $request->getRequestStatus());
    $this->assertEquals('remote_one', $request->getTranslatorProvider()->id());

    // Check that for another node, we can make a new request.
    $second_node = $this->createBasicTestNode();
    $this->drupalGet($second_node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->assertSession()->fieldEnabled('Translator');
    $select->selectOption('remote_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('New translation request using Remote one');
    $this->getSession()->getPage()->checkField('French');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent');
    $this->assertSession()->addressEquals('/en/node/' . $second_node->id() . '/translations/remote');
    $this->assertSession()->elementTextEquals('css', 'form h3', 'Ongoing remote translation request via Remote one.');
    $this->assertSession()->pageTextContains('No new translation request can be made because there is already an active translation request for this entity version.');
    $this->assertSession()->fieldDisabled('Translator');

    // Mimic the arrival of the translation in Bulgarian for the first node.
    TestRemoteTranslationMockHelper::translateRequest($request, 'bg');
    $request->save();
    $request_storage->resetCache();
    $request = $request_storage->load($request->id());
    $target_languages = $request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('bg', 'Review'), $target_languages['bg']);
    $this->assertEquals(new LanguageWithStatus('fr', 'Requested'), $target_languages['fr']);
    // Only one language has been translated so the request is still active.
    $this->assertEquals('Requested', $request->getRequestStatus());

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');

    // Assert the request status table is still active because only one
    // language has been translated.
    $this->assertRequestStatusTable([
      'Requested',
      'Remote one',
    ]);

    // Assert that the Bulgarian translation can now be reviewed because
    // it is marked as such and the user has the correct permissions.
    $expected_languages['bg']['review'] = TRUE;
    $expected_languages['bg']['status'] = 'Review';
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Review the Bulgarian translation.
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="bg"] a')->click();

    // Assert that we have the 3 buttons because the oe_translator has all the
    // permissions.
    $this->assertSession()->buttonExists('Save and accept');
    $this->assertSession()->buttonExists('Save and synchronise');
    $this->assertSession()->buttonExists('Preview');

    // Remove the "accept" permission and assert the button is missing.
    $role = Role::load('oe_translator');
    $role->revokePermission('accept translation request');
    $role->save();
    $this->getSession()->reload();

    $this->assertSession()->buttonNotExists('Save and accept');
    $this->assertSession()->buttonExists('Save and synchronise');
    // Do the same for the sync permission.
    \Drupal::service('cache_tags.invalidator')->resetChecksums();
    $role->revokePermission('sync translation request');
    $role->save();
    $this->getSession()->reload();
    $this->assertSession()->buttonNotExists('Save and accept');
    $this->assertSession()->buttonNotExists('Save and synchronise');

    // Add back the permissions.
    \Drupal::service('cache_tags.invalidator')->resetChecksums();
    $role->grantPermission('accept translation request');
    $role->grantPermission('sync translation request');
    $role->save();
    $this->getSession()->reload();

    // Assert we have all the fields there with the dummy translations.
    $fields = [];
    $fields['title'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Title']",
      'value' => 'Full translation node',
    ];
    $fields['address_given_name'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Address / First name']",
      'value' => 'The first name',
    ];
    $fields['address_family_name'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Address / Last name']",
      'value' => 'The last name',
    ];
    $fields['ott_content_reference'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Content reference / Title']",
      'value' => 'Referenced node',
    ];
    $fields['ott_inner_paragraphs__0__ott_inner_paragraph_ott__0'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Top level paragraphs / (0) / Demo paragraph type / Inner Paragraphs / (0) / Demo inner paragraph type / Inner paragraph field']",
      'value' => 'child field value 1',
    ];
    $fields['ott_inner_paragraphs__0__ott_inner_paragraphs__0__ott_inner_paragraph_ott__0'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Top level paragraphs / (0) / Demo paragraph type / Inner Paragraphs / (0) / Demo inner paragraph type / Inner Paragraphs / (0) / Demo inner paragraph type / Inner paragraph field']",
      'value' => 'grandchild field value 1',
    ];
    $fields['ott_inner_paragraphs__0__ott_inner_paragraphs__0__ott_inner_paragraph_ott__1'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Top level paragraphs / (0) / Demo paragraph type / Inner Paragraphs / (0) / Demo inner paragraph type / Inner Paragraphs / (1) / Demo inner paragraph type / Inner paragraph field']",
      'value' => 'grandchild field value 2',
    ];
    $fields['ott_inner_paragraphs__0__ott_inner_paragraph_ott__1'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Top level paragraphs / (0) / Demo paragraph type / Inner Paragraphs / (1) / Demo inner paragraph type / Inner paragraph field']",
      'value' => 'child field value 2',
    ];
    $fields['ott_top_level_paragraphs__0__ott_top_level_paragraph_ott__0'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Top level paragraphs / (0) / Demo paragraph type / Top level paragraph field']",
      'value' => 'top field value 1',
    ];
    $fields['ott_top_level_paragraphs__1__ott_top_level_paragraph_ott__0'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Top level paragraphs / (1) / Demo paragraph type / Top level paragraph field']",
      'value' => 'top field value 2',
    ];

    foreach ($fields as $key => $data) {
      $table_header = $this->getSession()->getPage()->find('xpath', $data['xpath']);
      if (!$table_header) {
        $this->fail(sprintf('The form label for the "%s" field was not found on the page.', $key));
      }

      $table = $table_header->getParent()->getParent()->getParent();
      $source = $table->find('xpath', "//textarea[contains(@name,'[source]')]");
      if (!$source) {
        $this->fail(sprintf('The source element for the "%s" field was not found on the page.', $key));
      }
      $this->assertEquals($data['value'], $source->getText());

      $translation = $table->find('xpath', "//textarea[contains(@name,'[translation]')]");
      if (!$translation) {
        $this->fail(sprintf('The translation element for the "%s" field was not found on the page.', $key));
      }

      $this->assertEquals($data['value'] . ' - bg', $translation->getText());
      // The translation element should not be disabled.
      $this->assertFalse($translation->hasAttribute('disabled'));
    }

    // Update the title while in review.
    $this->getSession()->getPage()->fillField('title|0|value[translation]', 'Full translation node - bg - update in review');

    // Accept the translation and assert it got accepted.
    $this->getSession()->getPage()->pressButton('Save and accept');
    $this->assertSession()->pageTextContains('The translation in Bulgarian has been accepted.');
    $this->assertSession()->addressEquals('/en/node/2/translations/remote');
    $expected_languages['bg']['status'] = 'Accepted';
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Assert that we don't yet have translations.
    $node_storage->resetCache();
    $node = $node_storage->load($node->id());
    $this->assertCount(1, $node->getTranslationLanguages());

    // Go the dashboard and assert we see the ongoing request.
    $this->clickLink('Translate');
    $this->assertSession()->pageTextContains('Ongoing remote translation requests');
    $expected_ongoing = [
      'translator' => 'Remote one',
      'status' => 'Requested',
      'title' => 'Full translation node',
      'title_url' => $node->toUrl()->toString(),
      'revision' => $node->getRevisionId(),
      'is_default' => 'Yes',
    ];
    $this->assertOngoingTranslations([$expected_ongoing]);

    // Assert we have the title change in the translation data.
    // We navigate from the dashboard.
    $this->getSession()->getPage()->find('css', 'table.ongoing-remote-translation-requests-table')->clickLink('View');
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="bg"] a')->click();
    $this->assertSession()->fieldValueEquals('title|0|value[translation]', 'Full translation node - bg - update in review');

    // Update again the title and preview the translation.
    $this->getSession()->getPage()->fillField('title|0|value[translation]', 'Full translation node - bg - update before preview');
    $this->getSession()->getPage()->pressButton('Preview');
    $this->assertSession()->pageTextContains('Full translation node - bg - update before preview');
    $this->assertSession()->pageTextContains('Referenced node - bg');
    $this->assertSession()->pageTextContains('grandchild field value 1 - bg');
    $this->assertSession()->pageTextContains('grandchild field value 2 - bg');
    $this->assertSession()->pageTextContains('child field value 2 - bg');
    $this->assertSession()->pageTextContains('child field value 2 - bg');
    $this->assertSession()->pageTextContains('top field value 1 - bg');
    $this->assertSession()->pageTextContains('top field value 2 - bg');

    // Go back and update again the title before syncing the translation.
    $this->getSession()->back();
    $this->getSession()->getPage()->fillField('title|0|value[translation]', 'Full translation node - bg - update before syncing');
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('The translation in Bulgarian has been synchronised.');
    $this->assertSession()->addressEquals('/en/translation-request/' . $request->id());
    $expected_languages['bg']['status'] = 'Synchronised';
    $expected_languages['bg']['review'] = FALSE;
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);
    // The request status has not changed.
    $request_storage->resetCache();
    $request = $request_storage->load($request->id());
    $this->assertEquals('Requested', $request->getRequestStatus());

    // Now we have a node translation.
    $node_storage->resetCache();
    $node = $node_storage->load($node->id());
    $this->assertCount(2, $node->getTranslationLanguages());
    $this->assertTrue($node->hasTranslation('bg'));
    $this->drupalGet('/bg/node/' . $node->id(), ['external' => FALSE]);

    // Assert that we can see all the translated values.
    foreach ($fields as $key => $data) {
      if ($key === 'title') {
        $this->assertSession()->pageTextContains('Full translation node - bg - update before syncing');
        continue;
      }
      $this->assertSession()->pageTextContains($data['value'] . ' - bg');
    }

    // Translate in French and sync the translation (directly without
    // accepting first).
    TestRemoteTranslationMockHelper::translateRequest($request, 'fr');
    $request->save();
    $request_storage->resetCache();
    $request = $request_storage->load($request->id());
    $target_languages = $request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('bg', 'Synchronised'), $target_languages['bg']);
    $this->assertEquals(new LanguageWithStatus('fr', 'Review'), $target_languages['fr']);
    // Both languages have been translated so the request is has been marked
    // as translated.
    $this->assertEquals('Translated', $request->getRequestStatus());

    $this->drupalGet('/node/' . $node->id() . '/translations/remote');

    // Assert that the request table has been updated to show that the status
    // is now translated.
    $this->assertRequestStatusTable([
      'Translated',
      'Remote one',
    ]);

    $expected_languages['bg']['review'] = FALSE;
    $expected_languages['bg']['status'] = 'Synchronised';
    $expected_languages['fr']['review'] = TRUE;
    $expected_languages['fr']['status'] = 'Review';
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);
    $this->getSession()->getPage()->clickLink('Review');
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('The translation in French has been synchronised.');
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations/remote');
    // By now, the request has been synced entirely so it won't show up
    // anymore so we have to manually go to it.
    $this->drupalGet($request->toUrl());
    $expected_languages['fr']['status'] = 'Synchronised';
    $expected_languages['fr']['review'] = FALSE;
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);
    // The request status has changed because all languages have been synced.
    $request_storage->resetCache();
    $request = $request_storage->load($request->id());
    $this->assertEquals('Finished', $request->getRequestStatus());
    $target_languages = $request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('bg', 'Synchronised'), $target_languages['bg']);
    $this->assertEquals(new LanguageWithStatus('fr', 'Synchronised'), $target_languages['fr']);

    // We now also have the FR translation.
    $node_storage->resetCache();
    $node = $node_storage->load($node->id());
    $this->assertCount(3, $node->getTranslationLanguages());
    $this->assertTrue($node->hasTranslation('fr'));

    // We don't have any ongoing requests on the dashboard anymore.
    $this->clickLink('Translate');
    $this->assertSession()->pageTextContains('There are no ongoing remote translation requests');

    // Even if the first node got all its translations, it didn't change the
    // second node which still cannot have new requests made because of an
    // ongoing request.
    $this->drupalGet($second_node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->assertSession()->fieldDisabled('Translator');
  }

  /**
   * Tests the translation flow with parallel request over many revisions.
   */
  public function testMultipleTranslationFlow(): void {
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $request_storage = \Drupal::entityTypeManager()->getStorage('oe_translation_request');

    $node = $this->createBasicTestNode();
    $first_revision = $node->getRevisionId();
    $this->drupalGet($node->toUrl());
    $this->clickLink('Translate');
    $this->clickLink('Remote translations');

    // Send a translation request in French only with Remote one.
    $this->getSession()->getPage()->selectFieldOption('Translator', 'remote_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->checkField('French');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent');

    // Edit the node and start a new revision.
    $this->drupalGet($node->toUrl('edit-form'));
    $node->set('title', 'Updated basic translation node');
    $node->setNewRevision(TRUE);
    $node->save();

    // Visit the dashboard to assert we see the correct info.
    $this->drupalGet($node->toUrl());
    $this->clickLink('Translate');

    // Assert the ongoing requests.
    $revision = $node_storage->loadRevision($first_revision);
    $expected_ongoing = [
      'translator' => 'Remote one',
      'status' => 'Requested',
      'title' => 'Basic translation node',
      'title_url' => $revision->toUrl('revision')->toString(),
      // It is the initial revision ID.
      'revision' => $revision->getRevisionId(),
      // It is no longer the default revision.
      'is_default' => 'No',
    ];
    $this->assertOngoingTranslations([$expected_ongoing]);

    // We only have the source translation in the existing translations list.
    $this->assertDashboardExistingTranslations([
      'en' => ['title' => 'Updated basic translation node'],
    ]);

    // Assert we cannot make a new request while there is an ongoing one.
    $this->clickLink('Remote translations');
    $this->assertSession()->pageTextContains('No new translation request can be made because there is already an active translation request for this entity version.');
    $this->assertSession()->fieldDisabled('Translator');

    // Translate the request so that we can make a new one.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($revision, TRUE);
    $this->assertCount(1, $requests);
    $request = reset($requests);
    TestRemoteTranslationMockHelper::translateRequest($request, 'fr');
    $request->save();

    // Make a new request on the new revision.
    $this->getSession()->reload();
    $this->getSession()->getPage()->selectFieldOption('Translator', 'remote_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->checkField('French');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent');
    $this->clickLink('Dashboard');

    // Assert we see both the active and translated requests.
    $translated_request = $expected_ongoing;
    $ongoing_request = $expected_ongoing;
    $translated_request['status'] = 'Translated';
    $ongoing_request['title'] = 'Updated basic translation node';
    $ongoing_request['title_url'] = $node->toUrl()->toString();
    $ongoing_request['revision'] = $node->getRevisionId();
    $ongoing_request['is_default'] = 'Yes';
    $this->assertOngoingTranslations([$translated_request, $ongoing_request]);

    // Translate and sync the latest revision translation.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $this->assertCount(1, $requests);
    $request = reset($requests);
    TestRemoteTranslationMockHelper::translateRequest($request, 'fr');
    $request->save();
    $this->clickLink('Remote translations');

    // We now have 2 requests on the remote translations page, both translated.
    $ongoing_request['status'] = 'Translated';
    $this->assertOngoingTranslations([$translated_request, $ongoing_request]);

    // Sync the latest translation request.
    $this->getSession()->getPage()->find('xpath', "//tr[2]/td[3]")->clickLink('View');
    $this->getSession()->getPage()->clickLink('Review');
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('The translation in French has been synchronised.');
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Dashboard');

    // Assert we only have again the first, translated request on the dashboard.
    $this->assertOngoingTranslations([$translated_request]);

    // But now we have a translation as well in the existing table.
    $this->assertDashboardExistingTranslations([
      'en' => ['title' => 'Updated basic translation node'],
      'fr' => ['title' => 'Updated basic translation node - fr'],
    ]);

    // Assert the node has translation only on the most recent revision.
    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface $revision */
    $revision = $node_storage->loadRevision($first_revision);
    $this->assertCount(1, $revision->getTranslationLanguages());
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($node->id());
    $this->assertCount(2, $node->getTranslationLanguages());
    $this->assertTrue($node->hasTranslation('fr'));
    $this->assertEquals('Updated basic translation node - fr', $node->getTranslation('fr')->label());

    // Translate and sync also the older ongoing translation.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($revision, TRUE);
    $this->assertCount(1, $requests);
    $request = reset($requests);
    TestRemoteTranslationMockHelper::translateRequest($request, 'fr');
    $request->save();
    $this->clickLink('Dashboard');
    $this->getSession()->getPage()->find('css', 'table.ongoing-remote-translation-requests-table')->clickLink('View');
    // There is only one language to review.
    $this->getSession()->getPage()->clickLink('Review');
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('The translation in French has been synchronised.');
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Dashboard');
    $this->assertSession()->pageTextContains('There are no ongoing remote translation requests');

    // Assert each revision has the correct translation.
    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface $revision */
    $revision = $node_storage->loadRevision($first_revision);
    $this->assertCount(2, $revision->getTranslationLanguages());
    $this->assertTrue($revision->hasTranslation('fr'));
    $this->assertEquals('Basic translation node - fr', $revision->getTranslation('fr')->label());
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($node->id());
    $this->assertCount(2, $node->getTranslationLanguages());
    $this->assertTrue($node->hasTranslation('fr'));
    $this->assertEquals('Updated basic translation node - fr', $node->getTranslation('fr')->label());

    // Assert that in this process, no new node revisions were created.
    $this->assertCount(2, $node_storage->revisionIds($node));
  }

  /**
   * Tests the select all or none of the language checkboxes.
   */
  public function testLanguageCheckboxesSelect(): void {
    $node = $this->createBasicTestNode();
    $this->drupalGet($node->toUrl());
    $this->clickLink('Translate');
    $this->clickLink('Remote translations');

    $select = $this->assertSession()->selectExists('Translator');
    $select->selectOption('remote_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('New translation request using Remote one');

    $languages = [
      'all' => 'Select all',
    ];
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      if ($language->getId() === 'en') {
        continue;
      }
      $languages[$language->getId()] = $language->getName();
    }

    foreach ($languages as $language => $name) {
      $this->assertSession()->fieldExists('translator_configuration[remote_one][languages][' . $language . ']');
      $this->assertSession()->checkboxNotChecked('translator_configuration[remote_one][languages][' . $language . ']');
    }

    $checkbox = $this->getSession()->getPage()->find('css', 'input[name="translator_configuration[remote_one][languages][all]"]');
    $this->assertEquals('Select all', $checkbox->getParent()->find('css', 'label')->getText());

    // Select all the languages.
    $checkbox->check();
    $this->getSession()->wait(100);

    // Assert all checkboxes have been checked.
    foreach ($languages as $language => $name) {
      $this->assertSession()->checkboxChecked('translator_configuration[remote_one][languages][' . $language . ']');
    }
    // The "Select all" checkbox got renamed.
    $this->assertEquals('Select none', $checkbox->getParent()->find('css', 'label')->getText());

    // Unselect all.
    $checkbox->uncheck();
    $this->getSession()->wait(100);
    foreach ($languages as $language => $name) {
      $this->assertSession()->checkboxNotChecked('translator_configuration[remote_one][languages][' . $language . ']');
    }
    // The "Select all" checkbox got renamed back.
    $this->assertEquals('Select all', $checkbox->getParent()->find('css', 'label')->getText());
  }

  /**
   * Tests that the translation sync process throws an event.
   *
   * Ensures that when we sync translations, we pass also the original
   * translation data to the event subscribers so that they can access in the
   * saveData method any extra metadata values they set in the extract method.
   * This is because if the translation comes from a remote source, the save
   * method will not have in the data array this metadata.
   */
  public function testTranslationSourceSyncWithEvent(): void {
    \Drupal::state()->set('oe_translation_remote_test_set_fake_value_on_node', TRUE);
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $node = $this->createFullTestNode();
    $this->drupalGet($node->toUrl());
    $this->clickLink('Translate');
    $this->clickLink('Remote translations');

    // Send a translation request in French only with Remote one.
    $this->getSession()->getPage()->selectFieldOption('Translator', 'remote_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->checkField('French');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent');

    // Translate the request.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $this->assertCount(1, $requests);
    /** @var \Drupal\oe_translation_remote\TranslationRequestRemoteInterface $request */
    $request = reset($requests);
    TestRemoteTranslationMockHelper::translateRequest($request, 'fr');
    // Remove from the translated data the #fake_value to mimic the fact that
    // a remote translator would not be sending this value.
    $translated_data = $request->getTranslatedData();
    $this->assertEquals('test fake value', $translated_data['fr']['oe_translation_test_field']['#fake_value']);
    unset($translated_data['fr']['oe_translation_test_field']['#fake_value']);
    $request->setTranslatedData('fr', $translated_data['fr']);
    $request->save();

    // Sync the language.
    $this->clickLink('Dashboard');
    $this->getSession()->getPage()->find('css', 'table.ongoing-remote-translation-requests-table')->clickLink('View');
    // There is only one language to review.
    $this->getSession()->getPage()->clickLink('Review');
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('The translation in French has been synchronised.');
    $node = $node_storage->load($node->id());
    // Assert that the TranslationSourceEventSubscriber added the #fake_value
    // to the node title. It's just a way of asserting that the subscriber
    // was able to access it from the original data array.
    $this->assertEquals('Full translation node test fake value', $node->label());
  }

}
