<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_remote\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Config\FileStorage;
use Drupal\field\Entity\FieldConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\oe_translation\LanguageWithStatus;
use Drupal\oe_translation_remote_test\TestRemoteTranslationMockHelper;
use Drupal\oe_translation_remote_test\TranslationRequestTestRemote;
use Drupal\Tests\oe_translation\FunctionalJavascript\TranslationTestBase;
use Drupal\Tests\oe_translation\Traits\TranslationsTestTrait;
use Drupal\user\Entity\Role;

/**
 * Tests the remote translations.
 */
class RemoteTranslationTest extends TranslationTestBase {

  use TranslationsTestTrait;

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

    // Mark the test entity reference field as embeddable for TMGMT to behave
    // as composite entities.
    $this->config('tmgmt_content.settings')
      ->set('embedded_fields', [
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
      'administer site configuration',
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

    // Create a new remote translator provider.
    $this->getSession()->getPage()->fillField('Name', 'Test provider');
    $this->getSession()->getPage()->fillField('A test configuration string', 'Plugin configuration.');
    $this->getSession()->getPage()->pressButton('Save');
    $this->getSession()->getPage()->fillField('Machine-readable name', 'test_provider');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Created the Test provider Remote Translator Provider.');

    // Make sure the configuration is saved properly.
    $remote_translator = \Drupal::entityTypeManager()->getStorage('remote_translation_provider')->load('test_provider');
    $this->assertEquals('Test provider', $remote_translator->label());
    $this->assertEquals('remote_two', $remote_translator->getProviderPlugin());
    $this->assertEquals(['configuration_string' => 'Plugin configuration.'], $remote_translator->getProviderConfiguration());

    // Edit the provider.
    $this->drupalGet($remote_translator->toUrl('edit-form'));
    $this->assertSession()->fieldValueEquals('Name', 'Test provider');
    $this->assertSession()->fieldValueEquals('A test configuration string', 'Plugin configuration.');
    $this->getSession()->getPage()->fillField('Name', 'Test provider edited');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Saved the Test provider edited Remote Translator Provider.');

    // Delete the provider.
    $this->drupalGet($remote_translator->toUrl('delete-form'));
    $this->getSession()->getPage()->pressButton('Delete');
    $this->assertSession()->pageTextContains('The remote translator provider Test provider edited has been deleted.');
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
      'active',
      'Remote one',
    ]);

    // We have the table of languages of this last request, all active but
    // none yet translated.
    $expected_languages = [];
    foreach (['bg', 'fr'] as $langcode) {
      $expected_languages[$langcode] = [
        'langcode' => $langcode,
        'status' => 'active',
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
    $this->assertEquals(new LanguageWithStatus('bg', 'active'), $target_languages['bg']);
    $this->assertEquals(new LanguageWithStatus('fr', 'active'), $target_languages['fr']);
    $this->assertEquals('active', $request->getRequestStatus());
    $this->assertEquals('remote_one', $request->getTranslatorProvider()->id());

    // Mimic the arrival of the translation in Bulgarian.
    TestRemoteTranslationMockHelper::translateRequest($request, 'bg');
    $request->save();
    $request_storage->resetCache();
    $request = $request_storage->load($request->id());
    $target_languages = $request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('bg', 'review'), $target_languages['bg']);
    $this->assertEquals(new LanguageWithStatus('fr', 'active'), $target_languages['fr']);
    // Only one language has been translated so the request is still active.
    $this->assertEquals('active', $request->getRequestStatus());

    $this->getSession()->reload();

    // Assert the request status table is still active because only one
    // language has been translated.
    $this->assertRequestStatusTable([
      'active',
      'Remote one',
    ]);

    // Assert that the Bulgarian translation can now be reviewed because
    // it is marked as such and the user has the correct permissions.
    $expected_languages['bg']['review'] = TRUE;
    $expected_languages['bg']['status'] = 'review';
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Review the Bulgarian translation.
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="bg"] a')->click();

    // Assert that we have the 2 buttons because the oe_translator has all the
    // permissions.
    $this->assertSession()->buttonExists('Save and accept');
    $this->assertSession()->buttonExists('Save and synchronize');

    // Remove the "accept" permission and assert the button is missing.
    $role = Role::load('oe_translator');
    $role->revokePermission('accept translation request');
    $role->save();
    $this->getSession()->reload();

    $this->assertSession()->buttonNotExists('Save and accept');
    $this->assertSession()->buttonExists('Save and synchronize');
    // Do the same for the sync permission.
    $role->revokePermission('sync translation request');
    $role->save();
    $this->getSession()->reload();
    $this->assertSession()->buttonNotExists('Save and accept');
    $this->assertSession()->buttonNotExists('Save and synchronize');

    // Add back the permissions.
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
      'xpath' => "//table//th[normalize-space(text()) = 'Top level paragraphs / Delta #0 / Inner Paragraphs / Delta #0 / Inner paragraph field']",
      'value' => 'child field value 1',
    ];
    $fields['ott_inner_paragraphs__0__ott_inner_paragraphs__0__ott_inner_paragraph_ott__0'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Top level paragraphs / Delta #0 / Inner Paragraphs / Delta #0 / Inner Paragraphs / Delta #0 / Inner paragraph field']",
      'value' => 'grandchild field value 1',
    ];
    $fields['ott_inner_paragraphs__0__ott_inner_paragraphs__0__ott_inner_paragraph_ott__1'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Top level paragraphs / Delta #0 / Inner Paragraphs / Delta #0 / Inner Paragraphs / Delta #1 / Inner paragraph field']",
      'value' => 'grandchild field value 2',
    ];
    $fields['ott_inner_paragraphs__0__ott_inner_paragraph_ott__1'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Top level paragraphs / Delta #0 / Inner Paragraphs / Delta #1 / Inner paragraph field']",
      'value' => 'child field value 2',
    ];
    $fields['ott_top_level_paragraphs__0__ott_top_level_paragraph_ott__0'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Top level paragraphs / Delta #0 / Top level paragraph field']",
      'value' => 'top field value 1',
    ];
    $fields['ott_top_level_paragraphs__1__ott_top_level_paragraph_ott__0'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Top level paragraphs / Delta #1 / Top level paragraph field']",
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
    }

    // Accept the translation and assert it got accepted.
    $this->getSession()->getPage()->pressButton('Save and accept');
    $this->assertSession()->pageTextContains('The translation in Bulgarian has been accepted.');
    $this->assertSession()->addressEquals('/en/translation-request/' . $request->id() . '/review/bg');

    $this->getSession()->getPage()->clickLink('Translation request for Full translation node');
    $expected_languages['bg']['status'] = 'accepted';
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
      'status' => 'active',
      'title' => 'Full translation node',
      'title_url' => $node->toUrl()->toString(),
      'revision' => $node->getRevisionId(),
      'is_default' => 'Yes',
    ];
    $this->assertOngoingTranslations([$expected_ongoing]);

    // Sync the translation and assert we go to the request page. We navigate
    // from the dashboard.
    $this->getSession()->getPage()->find('css', 'table.ongoing-remote-translation-requests-table')->clickLink('View');
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="bg"] a')->click();
    $this->getSession()->getPage()->pressButton('Save and synchronize');
    $this->assertSession()->pageTextContains('The translation in Bulgarian has been synchronized.');
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations/remote');
    $expected_languages['bg']['status'] = 'synchronized';
    $expected_languages['bg']['review'] = FALSE;
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);
    // The request status has not changed.
    $request_storage->resetCache();
    $request = $request_storage->load($request->id());
    $this->assertEquals('active', $request->getRequestStatus());

    // Now we have a node translation.
    $node_storage->resetCache();
    $node = $node_storage->load($node->id());
    $this->assertCount(2, $node->getTranslationLanguages());
    $this->assertTrue($node->hasTranslation('bg'));
    $this->drupalGet('/bg/node/' . $node->id(), ['external' => FALSE]);

    // Assert that we can see all the translated values.
    foreach ($fields as $key => $data) {
      $this->assertSession()->pageTextContains($data['value'] . ' - bg');
    }

    // Translate in French and sync the translation (directly without
    // accepting first).
    TestRemoteTranslationMockHelper::translateRequest($request, 'fr');
    $request->save();
    $request_storage->resetCache();
    $request = $request_storage->load($request->id());
    $target_languages = $request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('bg', 'synchronized'), $target_languages['bg']);
    $this->assertEquals(new LanguageWithStatus('fr', 'review'), $target_languages['fr']);
    // Both languages have been translated so the request is has been marked
    // as translated.
    $this->assertEquals('translated', $request->getRequestStatus());

    $this->drupalGet('/node/' . $node->id() . '/translations/remote');

    // Assert that the request table has been updated to show that the status
    // is now translated.
    $this->assertRequestStatusTable([
      'translated',
      'Remote one',
    ]);

    $expected_languages['bg']['review'] = FALSE;
    $expected_languages['bg']['status'] = 'synchronized';
    $expected_languages['fr']['review'] = TRUE;
    $expected_languages['fr']['status'] = 'review';
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);
    $this->getSession()->getPage()->clickLink('Review');
    $this->getSession()->getPage()->pressButton('Save and synchronize');
    $this->assertSession()->pageTextContains('The translation in French has been synchronized.');
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations/remote');
    // By now, the request has been synced entirely so it won't show up
    // anymore so we have to manually go to it.
    $this->drupalGet($request->toUrl());
    $expected_languages['fr']['status'] = 'synchronized';
    $expected_languages['fr']['review'] = FALSE;
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);
    // The request status has changed because all languages have been synced.
    $request_storage->resetCache();
    $request = $request_storage->load($request->id());
    $this->assertEquals('finished', $request->getRequestStatus());
    $target_languages = $request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('bg', 'synchronized'), $target_languages['bg']);
    $this->assertEquals(new LanguageWithStatus('fr', 'synchronized'), $target_languages['fr']);

    // We now also have the FR translation.
    $node_storage->resetCache();
    $node = $node_storage->load($node->id());
    $this->assertCount(3, $node->getTranslationLanguages());
    $this->assertTrue($node->hasTranslation('fr'));

    // We don't have any ongoing requests on the dashboard anymore.
    $this->clickLink('Translate');
    $this->assertSession()->pageTextContains('There are no ongoing remote translation requests');
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
      'status' => 'active',
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
    $translated_request['status'] = 'translated';
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
    $ongoing_request['status'] = 'translated';
    $this->assertOngoingTranslations([$translated_request, $ongoing_request]);

    // Sync the latest translation request.
    $this->getSession()->getPage()->find('xpath', "//tr[td//text()[contains(., 'Updated basic translation node')]]")->clickLink('View');
    $this->getSession()->getPage()->clickLink('Review');
    $this->getSession()->getPage()->pressButton('Save and synchronize');
    $this->assertSession()->pageTextContains('The translation in French has been synchronized.');
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
    $this->getSession()->getPage()->pressButton('Save and synchronize');
    $this->assertSession()->pageTextContains('The translation in French has been synchronized.');
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

  /**
   * Asserts the ongoing translations table.
   *
   * @param array $languages
   *   The expected languages.
   */
  protected function assertOngoingTranslations(array $languages): void {
    $table = $this->getSession()->getPage()->find('css', 'table.ongoing-remote-translation-requests-table');
    $this->assertCount(count($languages), $table->findAll('css', 'tbody tr'));
    $rows = $table->findAll('css', 'tbody tr');
    foreach ($rows as $key => $row) {
      $cols = $row->findAll('css', 'td');
      $expected_info = $languages[$key];
      $this->assertEquals($expected_info['translator'], $cols[0]->getText());
      $this->assertEquals($expected_info['status'], $cols[1]->getText());
      $this->assertEquals($expected_info['title_url'], $cols[2]->findLink($expected_info['title'])->getAttribute('href'));
      $this->assertEquals($expected_info['revision'], $cols[3]->getText());
      $this->assertEquals($expected_info['is_default'], $cols[4]->getText());
      $this->assertTrue($cols[5]->hasLink('View'));
    }
  }

}
