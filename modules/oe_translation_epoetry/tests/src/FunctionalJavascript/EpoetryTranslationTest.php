<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_epoetry\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\node\Entity\Node;
use Drupal\oe_translation\Entity\TranslationRequest;
use Drupal\oe_translation\LanguageWithStatus;
use Drupal\oe_translation_epoetry\Plugin\Field\FieldType\ContactItem;
use Drupal\oe_translation_epoetry\RequestFactory;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetry;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\oe_translation_epoetry_mock\EpoetryTranslationMockHelper;
use Drupal\oe_translation_remote\Entity\RemoteTranslatorProvider;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Drupal\Tests\oe_translation\FunctionalJavascript\TranslationTestBase;
use Drupal\Tests\oe_translation\Traits\TranslationsTestTrait;
use Drupal\Tests\oe_translation_epoetry\EpoetryTranslationTestTrait;
use Drupal\Tests\oe_translation_remote\Traits\RemoteTranslationsTestTrait;

/**
 * Tests the remote translations via ePoetry.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 *
 * @group batch2
 */
class EpoetryTranslationTest extends TranslationTestBase {

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
    $provider->setProviderConfiguration($configuration);
    $provider->save();

    $this->user = $this->setUpTranslatorUser();
    $this->drupalLogin($this->user);
  }

  /**
   * Tests the ePoetry translation provider configuration form.
   */
  public function testEpoetryProviderConfiguration(): void {
    $user = $this->drupalCreateUser([
      'administer remote translators',
      'access administration pages',
      'access toolbar',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/structure/remote-translation-provider');
    $this->assertSession()->pageTextContains('Remote Translator Provider entities');
    $this->clickLink('Add Remote Translator Provider');
    $this->getSession()->getPage()->selectFieldOption('Plugin', 'ePoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Plugin configuration for ePoetry');

    $contact_fields = ContactItem::contactTypes();
    // Assert we have the contact form elements.
    foreach ($contact_fields as $field => $description) {
      $this->assertSession()->fieldExists($field);
      if (empty($field)) {
        $this->assertSession()->elementTextEquals('css', '#form-item-translator-configuration-epoetry-' . strtolower($field) . '--description', $description);
      }
    }

    // Assert we have the auto-accept checkbox.
    $this->assertSession()->fieldExists('Auto-accept translations');

    // Fill in the first 3 contact types.
    $this->getSession()->getPage()->fillField('Recipient', 'test_recipient');
    $this->getSession()->getPage()->fillField('Webmaster', 'test_webmaster');
    $this->getSession()->getPage()->fillField('Editor', 'test_editor');

    // Check the box for auto-accepting.
    $this->getSession()->getPage()->checkField('Auto-accept translations');

    // Set the title prefix and site ID.
    $this->getSession()->getPage()->fillField('Request title prefix', 'The title prefix');
    $this->getSession()->getPage()->fillField('Site ID', 'The site ID');

    // Create a new ePoetry provider.
    $this->getSession()->getPage()->fillField('Name', 'ePoetry provider');
    $this->getSession()->getPage()->find('css', '.admin-link .link')->press();
    $this->assertSession()->waitForField('Machine-readable name');
    $this->getSession()->getPage()->fillField('Machine-readable name', 'epoetry_provider');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Created the ePoetry provider Remote Translator Provider.');

    // Make sure the configuration is saved properly.
    $storage = \Drupal::entityTypeManager()->getStorage('remote_translation_provider');
    $storage->resetCache();
    $translator = $storage->load('epoetry_provider');
    $this->assertEquals('ePoetry provider', $translator->label());
    $this->assertEquals('epoetry', $translator->getProviderPlugin());
    $default_language_mapping = [];
    foreach (\Drupal::languageManager()->getLanguages() as $language) {
      $default_language_mapping[$language->getId()] = strtoupper($language->getId());
    }
    $this->assertEquals([
      'contacts' => [
        'Recipient' => 'test_recipient',
        'Webmaster' => 'test_webmaster',
        'Editor' => 'test_editor',
      ],
      'auto_accept' => TRUE,
      'title_prefix' => 'The title prefix',
      'site_id' => 'The site ID',
      'language_mapping' => $default_language_mapping,
    ], $translator->getProviderConfiguration());

    // Edit the provider.
    $this->drupalGet($translator->toUrl('edit-form'));
    $this->assertSession()->fieldValueEquals('Name', 'ePoetry provider');
    $this->assertSession()->fieldValueEquals('Recipient', 'test_recipient');
    $this->assertSession()->fieldValueEquals('Webmaster', 'test_webmaster');
    $this->assertSession()->fieldValueEquals('Editor', 'test_editor');
    $this->assertSession()->fieldValueEquals('Request title prefix', 'The title prefix');
    $this->assertSession()->fieldValueEquals('Site ID', 'The site ID');
    $this->getSession()->getPage()->fillField('Name', 'ePoetry provider edited');
    $this->getSession()->getPage()->uncheckField('Auto-accept translations');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Saved the ePoetry provider edited Remote Translator Provider.');
    $translator = \Drupal::entityTypeManager()->getStorage('remote_translation_provider')->load('epoetry_provider');
    $this->assertEquals([
      'contacts' => [
        'Recipient' => 'test_recipient',
        'Webmaster' => 'test_webmaster',
        'Editor' => 'test_editor',
      ],
      'auto_accept' => FALSE,
      'title_prefix' => 'The title prefix',
      'site_id' => 'The site ID',
      'language_mapping' => $default_language_mapping,
    ], $translator->getProviderConfiguration());
  }

  /**
   * Tests that some configuration is pre-set from the provider configuration.
   */
  public function testEpoetryPresetTranslationForm(): void {
    $node = $this->createBasicTestNode();
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->assertSession()->pageTextContains('New translation request using ePoetry');

    // The contacts fields are empty and the auto-accept checkbox is enabled
    // because the provider is not yet configured.
    $this->assertSession()->fieldEnabled('Auto accept translations');
    $this->assertSession()->elementTextEquals('css', '.form-item-translator-configuration-epoetry-auto-accept-value .description', 'Choose if incoming translations should be auto-accepted');
    $this->assertSession()->fieldExists('Auto sync translations');
    // The deadline field has a "Date" hidden label.
    $this->assertSession()->fieldExists('Date');
    $this->assertSession()->fieldExists('Message');
    $contact_fields = [
      'Author' => FALSE,
      'Requester' => FALSE,
      'Recipient' => TRUE,
      'Editor' => TRUE,
      'Webmaster' => TRUE,
    ];
    foreach ($contact_fields as $field => $exists) {
      if ($exists) {
        $this->assertSession()->fieldValueEquals($field, '');
      }
      else {
        $this->assertSession()->fieldNotExists($field);
      }
    }

    // Update the provider configuration to provide default contacts and
    // pre-configure the auto-accept.
    $translator = RemoteTranslatorProvider::load('epoetry');
    $translator->setProviderConfiguration([
      'contacts' => [
        'Recipient' => 'test_recipient',
        'Webmaster' => 'test_webmaster',
        'Editor' => 'test_editor',
      ],
      'auto_accept' => TRUE,
    ]);
    $translator->save();

    // Now the contact fields are pre-filled and the auto-accept checkbox
    // is disabled.
    $this->getSession()->reload();
    $this->assertSession()->pageTextContains('New translation request using ePoetry');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->assertSession()->fieldValueEquals($field, $value);
    }
    $this->assertSession()->fieldDisabled('Auto accept translations');
    $this->assertSession()->elementTextEquals('css', '.form-item-translator-configuration-epoetry-auto-accept-value .description', 'Choose if incoming translations should be auto-accepted. The auto-accept feature is enabled at site level. All requests will be auto-accepted.');
  }

  /**
   * Tests the main remote translation flow using ePoetry.
   *
   * This is similar to RemoteTranslationTest::testSingleTranslationFlow but
   * simplified and focusing more on the ePoetry specific aspects.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function testEpoetrySingleTranslationFlow(): void {
    // Set PT language mapping.
    $translator = RemoteTranslatorProvider::load('epoetry');
    $configuration = $translator->getProviderConfiguration();
    $configuration['language_mapping']['pt-pt'] = 'PT';
    $translator->setProviderConfiguration($configuration);
    $translator->save();

    $node = $this->createBasicTestNode();
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');

    $select = $this->assertSession()->selectExists('Translator');
    // The ePoetry translator is preselected because it's the only one.
    $this->assertEquals('epoetry', $select->find('css', 'option[selected]')->getValue());
    $this->assertSession()->pageTextContains('New translation request using ePoetry');

    // Pick a deadline, contacts and a message.
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }

    // Assert the languages' validation.
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('Please select at least one language.');

    // Select 2 languages.
    $this->getSession()->getPage()->checkField('Bulgarian');
    $this->getSession()->getPage()->checkField('Portuguese');

    // Assert the message length is under 1700 characters.
    $this->getSession()->getPage()->fillField('Message', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cup');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('Please keep the message under 1700 characters.');

    // Put a smaller message and send.
    $this->getSession()->getPage()->fillField('Message', 'Message to the provider');
    $this->getSession()->getPage()->pressButton('Save and send');

    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    // Assert that the XML request we built is correct.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(1, $requests);
    $xml = reset($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:createLinguisticRequest><requestDetails><title>A title prefix: A site ID - Basic translation node</title><internalReference>Translation request 1</internalReference><requestedDeadline>2032-10-10T23:59:00+02:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><comment>Message to the provider. Page URL: http://web:8080/build/en/basic-translation-node</comment><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic-translation-node.html</fileName><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMSIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJFTiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDE8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0xIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzFdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTVYxYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2032-10-10T23:59:00+02:00" trackChanges="false"><language>BG</language></product><product requestedDeadline="2032-10-10T23:59:00+02:00" trackChanges="false"><language>PT</language></product></products></requestDetails><applicationName>digit</applicationName><templateName>WEBTRA</templateName></ns1:createLinguisticRequest></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

    // Assert that we got back a correct response and our request was correctly
    // updated.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $this->assertCount(1, $requests);
    $request = reset($requests);
    $this->assertInstanceOf(TranslationRequestEpoetryInterface::class, $request);
    $this->assertEquals('en', $request->getSourceLanguageCode());
    $target_languages = $request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('bg', 'Requested'), $target_languages['bg']);
    $this->assertEquals(new LanguageWithStatus('pt-pt', 'Requested'), $target_languages['pt-pt']);
    $this->assertEquals('Requested', $request->getRequestStatus());
    $this->assertEquals('SenttoDGT', $request->getEpoetryRequestStatus());
    $this->assertEquals('epoetry', $request->getTranslatorProvider()->id());
    $this->assertFalse($request->isAutoAccept());
    $this->assertFalse($request->isAutoSync());
    $this->assertEquals('2032-10-10', $request->getDeadline()->format('Y-m-d'));
    $this->assertEquals('Message to the provider', $request->getMessage());
    $this->assertEquals('DIGIT/' . date('Y') . '/1001/0/0/TRA', $request->getRequestId(TRUE));
    $this->assertEquals([
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ], $request->getContacts());

    // Assert the request status table.
    $this->assertRequestStatusTable([
      'Requested',
      'ePoetry',
      'SenttoDGT',
      'DIGIT/' . date('Y') . '/1001/0/0/TRA',
      'No',
      'No',
      '2032-Oct-10',
      // The mock link tu accept the request.
      'Accept',
    ]);

    // Assert the message.
    $this->assertSession()->pageTextContains('Message to provider');
    $expected_logs = [
      1 => [
        'Info',
        'The request has been sent successfully using the createLinguisticRequest request type. The dossier number started is 1001.',
        $this->user->label(),
      ],
      2 => [
        'Info',
        'Message from ePoetry: We have received your createLinguisticRequest. We will process it soon.',
        $this->user->label(),
      ],
    ];
    $this->assertLogMessagesTable($expected_logs);

    // We have the table of languages of this last request, all active but
    // none yet translated.
    $expected_languages = [];
    foreach (['bg', 'pt-pt'] as $langcode) {
      $expected_languages[$langcode] = [
        'langcode' => $langcode,
        'status' => 'Requested [in ePoetry]',
        'accepted_deadline' => 'N/A',
        'review' => FALSE,
      ];
    }
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Assert that we are storing the generated dossier in the state.
    $dossiers = RequestFactory::getEpoetryDossiers();
    $this->assertEquals([
      1001 => [
        'part' => 0,
        'code' => 'DIGIT',
        'year' => date('Y'),
      ],
    ], $dossiers);

    // Accept the request and the PT-PT language.
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EpoetryTranslationMockHelper::notifyRequest($request, [
      'type' => 'RequestStatusChange',
      'status' => 'Accepted',
    ]);
    EpoetryTranslationMockHelper::notifyRequest($request, [
      'type' => 'ProductStatusChange',
      'status' => 'Accepted',
      'language' => 'pt-pt',
    ]);

    $this->getSession()->reload();
    $this->assertRequestStatusTable([
      'Requested',
      'ePoetry',
      'Accepted',
      'DIGIT/' . date('Y') . '/1001/0/0/TRA',
      'No',
      'No',
      '2032-Oct-10',
      // The mock link tu cancel the request since it's been Accepted.
      'Cancel',
    ]);
    $expected_languages['pt-pt']['status'] = 'Accepted [in ePoetry]';
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Assert the extra logs.
    $expected_logs[3] = [
      'Info',
      'The request has been Accepted by ePoetry. Planning agent: test. Planning sector: DGT. Message: The request status has been changed to Accepted.',
      'Anonymous',
    ];
    $expected_logs[4] = [
      'Info',
      'The Portuguese product status has been updated to Accepted.',
      'Anonymous',
    ];
    $this->assertLogMessagesTable($expected_logs);

    // Mark the PT languages as ongoing (this will update the accepted
    // deadline).
    EpoetryTranslationMockHelper::notifyRequest($request, [
      'type' => 'ProductStatusChange',
      'status' => 'Ongoing',
      'language' => 'pt-pt',
    ]);
    $this->getSession()->reload();
    $expected_languages['pt-pt']['status'] = 'Ongoing';
    $expected_languages['pt-pt']['accepted_deadline'] = '2050-Apr-04';

    // Send the translation.
    EpoetryTranslationMockHelper::translateRequest($request, 'pt-pt');
    $this->getSession()->reload();
    $expected_languages['pt-pt']['status'] = 'Review';
    $expected_languages['pt-pt']['review'] = TRUE;
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Assert the extra logs.
    $expected_logs[5] = [
      'Info',
      'The Portuguese product status has been updated to Ongoing.',
      'Anonymous',
    ];
    $expected_logs[6] = [
      'Info',
      'The Portuguese translation has been delivered.',
      'Anonymous',
    ];

    $this->assertLogMessagesTable($expected_logs);

    // Sync the translation (first accept it).
    $this->getSession()->getPage()->clickLink('Review');
    $this->getSession()->getPage()->pressButton('Save and accept');
    $this->drupalGet($request->toUrl());
    $expected_logs[7] = [
      'Info',
      'The Portuguese translation has been accepted.',
      $this->user->label(),
    ];
    $this->assertLogMessagesTable($expected_logs);
    $this->getSession()->getPage()->clickLink('Review');
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('The translation in Portuguese has been synchronised.');
    $this->assertSession()->addressEquals('/en/translation-request/' . $request->id());
    $expected_languages['pt-pt']['status'] = 'Synchronised';
    $expected_languages['pt-pt']['review'] = FALSE;
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);
    $expected_logs[8] = [
      'Info',
      'The Portuguese translation has been synchronised with the content.',
      $this->user->label(),
    ];
    $this->assertLogMessagesTable($expected_logs);
    $node = Node::load($node->id());
    $this->assertTrue($node->hasTranslation('pt-pt'));
    $this->assertEquals('Basic translation node - PT', $node->getTranslation('pt-pt')->label());
  }

  /**
   * Tests the that we are creating new parts when we translate new nodes.
   *
   * This tests that when we translate nodes, we add parts to the same dossier
   * instead of always making a new linguistic requests. And that the parts
   * only go up to 30.
   */
  public function testAddPartsTranslationFlow(): void {
    // Create a node and mimic it having been translated via ePoetry.
    $first_node = $this->createBasicTestNode();
    $first_node->addTranslation('fr', ['title' => 'Basic translation node fr'] + $first_node->toArray());
    $first_node->setNewRevision(FALSE);
    $first_node->save();
    $request = $this->createNodeTranslationRequest($first_node, TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED, [
      [
        'langcode' => 'fr',
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SYNCHRONISED,
      ],
    ]);
    $request->save();
    // Mimic the setting in state of the newly created dossier.
    $dossiers[2000] = [
      'part' => 0,
      'code' => 'DIGIT',
      'year' => date('Y'),
    ];
    // Mimic that the above request has been made in the UI and store the
    // resulting dossier and mock last requested reference number.
    RequestFactory::setEpoetryDossiers($dossiers);
    \Drupal::state()->set('oe_translation_epoetry_mock.last_request_reference', 2000);

    // Now create another node and start translating it.
    $second_node = $this->createBasicTestNode();
    $this->drupalGet($second_node->toUrl('drupal:content-translation-overview'));
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
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    // Assert that the XML request we built is correct and it's using the
    // addNewPart.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(1, $requests);
    $xml = reset($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:addNewPartToDossier><dossier><requesterCode>DIGIT</requesterCode><number>2000</number><year>' . date('Y') . '</year></dossier><requestDetails><title>A title prefix: A site ID - Basic translation node</title><internalReference>Translation request 2</internalReference><requestedDeadline>2032-10-10T23:59:00+02:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><comment>Page URL: http://web:8080/build/en/basic-translation-node-0</comment><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic-translation-node.html</fileName><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMiIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJFTiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDI8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0yIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzJdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTWwxYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2032-10-10T23:59:00+02:00" trackChanges="false"><language>FR</language></product></products></requestDetails><applicationName>digit</applicationName></ns1:addNewPartToDossier></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

    // Assert that the state dossier got updated correctly.
    $dossiers = RequestFactory::getEpoetryDossiers();
    $this->assertEquals([
      2000 => [
        'part' => 1,
        'code' => 'DIGIT',
        'year' => date('Y'),
      ],
    ], $dossiers);

    // Assert the log messages.
    $expected_logs = [
      1 => [
        'Info',
        'The request has been sent successfully using the addNewPartToDossierRequest request type.',
        $this->user->label(),
      ],
      2 => [
        'Info',
        'Message from ePoetry: We have received your addNewPartToDossier. We will process it soon.',
        $this->user->label(),
      ],
    ];
    $this->assertLogMessagesTable($expected_logs);

    // Now create yet another node and translate it. The parts should increment.
    $third_node = $this->createBasicTestNode();
    $this->drupalGet($third_node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->getSession()->getPage()->checkField('German');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(2, $requests);
    $xml = end($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:addNewPartToDossier><dossier><requesterCode>DIGIT</requesterCode><number>2000</number><year>' . date('Y') . '</year></dossier><requestDetails><title>A title prefix: A site ID - Basic translation node</title><internalReference>Translation request 3</internalReference><requestedDeadline>2032-10-10T23:59:00+02:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><comment>Page URL: http://web:8080/build/en/basic-translation-node-1</comment><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic-translation-node.html</fileName><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMyIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJFTiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDM8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0zIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzNdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTTExYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2032-10-10T23:59:00+02:00" trackChanges="false"><language>DE</language></product></products></requestDetails><applicationName>digit</applicationName></ns1:addNewPartToDossier></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);
    $dossiers = RequestFactory::getEpoetryDossiers();
    $this->assertEquals([
      2000 => [
        'part' => 2,
        'code' => 'DIGIT',
        'year' => date('Y'),
      ],
    ], $dossiers);

    // Mimic that we have reached to part 30 and the dossier should be reset.
    $dossiers[2000]['part'] = 30;
    RequestFactory::setEpoetryDossiers($dossiers);

    // Create a new node and send a translation request.
    $fourth_node = $this->createBasicTestNode();
    $this->drupalGet($fourth_node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->getSession()->getPage()->checkField('Italian');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    // Assert that the XML request we built is correct and it's using the
    // createNewLinguisticRequest.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(3, $requests);
    $xml = end($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:createLinguisticRequest><requestDetails><title>A title prefix: A site ID - Basic translation node</title><internalReference>Translation request 4</internalReference><requestedDeadline>2032-10-10T23:59:00+02:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><comment>Page URL: http://web:8080/build/en/basic-translation-node-2</comment><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic-translation-node.html</fileName><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iNCIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJFTiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDQ8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS00Ij4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzRdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTkYxYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2032-10-10T23:59:00+02:00" trackChanges="false"><language>IT</language></product></products></requestDetails><applicationName>digit</applicationName><templateName>WEBTRA</templateName></ns1:createLinguisticRequest></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

    $dossiers = RequestFactory::getEpoetryDossiers();
    $this->assertEquals([
      2000 => [
        'part' => 30,
        'code' => 'DIGIT',
        'year' => date('Y'),
      ],
      2001 => [
        'part' => 0,
        'code' => 'DIGIT',
        'year' => date('Y'),
      ],
    ], $dossiers);
  }

  /**
   * Tests that after a translation is made, we make a new version request.
   */
  public function testEpoetryNewVersionRequest(): void {
    // Create a node and mimic it having been translated via ePoetry.
    $node = $this->createBasicTestNode();
    $node->addTranslation('fr', ['title' => 'Basic translation node fr'] + $node->toArray());
    $node->setNewRevision(FALSE);
    $node->save();
    $request = $this->createNodeTranslationRequest($node, TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED, [
      [
        'langcode' => 'fr',
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SYNCHRONISED,
      ],
    ]);
    $request->save();

    // Make a new draft of the node and start a new translation.
    $node->set('title', 'Basic translation node - update');
    $node->setNewRevision(TRUE);
    $node->save();

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertDashboardExistingTranslations([
      'en' => ['title' => 'Basic translation node'],
      'fr' => ['title' => 'Basic translation node fr'],
    ]);

    $this->clickLink('Remote translations');
    $this->assertSession()->pageTextContains('New translation request using ePoetry');
    $this->assertSession()->pageTextContains(sprintf('You are making a request for a new version. The previous version was translated with the %s request ID.', $request->getRequestId(TRUE)));

    // Fill in request details and submit.
    $this->getSession()->getPage()->checkField('German');
    $this->getSession()->getPage()->checkField('Italian');

    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2034');
    $this->getSession()->getPage()->fillField('Message', 'Some message to the provider');
    $contact_fields = [
      'Recipient' => 'test_recipient2',
      'Webmaster' => 'test_webmaster2',
      'Editor' => 'test_editor2',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }

    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    // Assert that the XML request we built is correct.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(1, $requests);
    $xml = reset($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:createNewVersion><linguisticRequest><requestReference><dossier><requesterCode>DIGIT</requesterCode><number>2000</number><year>' . date('Y') . '</year></dossier><productType>TRA</productType><part>0</part></requestReference><requestDetails><title>A title prefix: A site ID - Basic translation node - update</title><internalReference>Translation request 2</internalReference><requestedDeadline>2034-10-10T23:59:00+02:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><comment>Some message to the provider. Page URL: http://web:8080/build/en/basic-translation-node-update</comment><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient2" contactRole="RECIPIENT"/><contact userId="test_webmaster2" contactRole="WEBMASTER"/><contact userId="test_editor2" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic-translation-node---update.html</fileName><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMiIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJFTiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDI8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0yIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzJdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTWwxYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlIC0gdXBkYXRlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2034-10-10T23:59:00+02:00" trackChanges="false"><language>DE</language></product><product requestedDeadline="2034-10-10T23:59:00+02:00" trackChanges="false"><language>IT</language></product></products></requestDetails></linguisticRequest><applicationName>digit</applicationName></ns1:createNewVersion></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);
    // Assert that we got back a correct response and our request was correctly
    // updated.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $this->assertCount(1, $requests);
    $update_request = reset($requests);
    $this->assertNotEquals($request->id(), $update_request->id());
    // The old request didn't get its status changed by this new update.
    $this->assertEquals('Executed', $request->getEpoetryRequestStatus());
    $this->assertInstanceOf(TranslationRequestEpoetryInterface::class, $update_request);
    $this->assertEquals('en', $update_request->getSourceLanguageCode());
    $target_languages = $update_request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('de', 'Requested'), $target_languages['de']);
    $this->assertEquals(new LanguageWithStatus('it', 'Requested'), $target_languages['it']);
    $this->assertEquals('Requested', $update_request->getRequestStatus());
    $this->assertEquals('SenttoDGT', $update_request->getEpoetryRequestStatus());
    $this->assertEquals('epoetry', $update_request->getTranslatorProvider()->id());
    $this->assertFalse($update_request->isAutoAccept());
    $this->assertFalse($update_request->isAutoSync());
    $this->assertEquals('2034-10-10', $update_request->getDeadline()->format('Y-m-d'));
    $this->assertEquals('Some message to the provider', $update_request->getMessage());
    // The version got updated, not the number.
    $this->assertEquals('DIGIT/' . date('Y') . '/2000/1/0/TRA', $update_request->getRequestId(TRUE));
    $this->assertEquals([
      'Recipient' => 'test_recipient2',
      'Webmaster' => 'test_webmaster2',
      'Editor' => 'test_editor2',
    ], $update_request->getContacts());

    // Assert the request status table.
    $this->assertRequestStatusTable([
      'Requested',
      'ePoetry',
      'SenttoDGT',
      // The version got updated, not the number.
      'DIGIT/' . date('Y') . '/2000/1/0/TRA',
      'No',
      'No',
      '2034-Oct-10',
      // The mock link to Accept.
      'Accept',
    ]);

    // We have the table of languages of this last request, all active but
    // none yet translated.
    $expected_languages = [];
    foreach (['de', 'it'] as $langcode) {
      $expected_languages[$langcode] = [
        'langcode' => $langcode,
        'status' => 'Requested [in ePoetry]',
        'accepted_deadline' => 'N/A',
        'review' => FALSE,
      ];
    }
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Assert the log messages.
    $expected_logs = [
      1 => [
        'Info',
        'The request has been sent successfully using the createNewVersionRequest request type.',
        $this->user->label(),
      ],
      2 => [
        'Info',
        'Message from ePoetry: We have received your createNewVersionRequest. We will process it soon.',
        $this->user->label(),
      ],
    ];
    $this->assertLogMessagesTable($expected_logs);
  }

  /**
   * Tests the access to create a new version for ongoing requests.
   *
   * In essence, tests that all criteria are met before a user can make a
   * createNewVersion request for an ongoing translation request. It doesn't
   * cover the cases in which a given request has already been translated by
   * ePoetry.
   */
  public function testAccessOngoingCreateVersion(): void {
    $cases = [
      'no new draft, sent to DGT request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_SENT,
        'create draft' => FALSE,
        'visible' => FALSE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'no new draft, accepted request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED,
        'create draft' => FALSE,
        'visible' => FALSE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'new draft, accepted request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED,
        'create draft' => TRUE,
        'visible' => TRUE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'new draft, executed request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_EXECUTED,
        'create draft' => TRUE,
        'visible' => FALSE,
        'ongoing' => FALSE,
        'finished' => TRUE,
      ],
      'new draft, executed request, requested status' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_EXECUTED,
        'create draft' => TRUE,
        'visible' => TRUE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'new draft, suspended request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_SUSPENDED,
        'create draft' => TRUE,
        'visible' => TRUE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'new draft, cancelled request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_CANCELLED,
        'create draft' => TRUE,
        'visible' => FALSE,
        'ongoing' => FALSE,
        'finished' => TRUE,
      ],
    ];

    foreach ($cases as $case => $case_info) {
      // Create the node from scratch.
      $node = $this->createBasicTestNode();

      // Make an active request and set the case epoetry status.
      $request = $this->createNodeTranslationRequest($node);
      $request->setEpoetryRequestStatus($case_info['epoetry_status']);
      if ($case_info['finished']) {
        $request->setRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_FINISHED);
      }
      $request->save();

      // Make a new draft if needed.
      if ($case_info['create draft']) {
        $node->set('title', 'Basic translation node - update - ' . $case);
        $node->setNewRevision(TRUE);
        $node->save();
      }

      $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
      $this->clickLink('Remote translations');

      if ($case_info['ongoing']) {
        $this->assertSession()->pageTextContains('Ongoing remote translation request via ePoetry');
      }
      else {
        $this->assertSession()->pageTextNotContains('Ongoing remote translation request via ePoetry');
      }

      if ($case_info['visible']) {
        $this->assertSession()->pageTextContains('Request an update');
        $this->assertSession()->linkExistsExact('Update');

        // If we are on the case where we should see the Update button, assert
        // also that if the request is not active anymore, we don't see it.
        foreach (['Translated', 'Finished', 'Failed'] as $status) {
          $request->setRequestStatus($status);
          $request->save();
        }

        $this->getSession()->reload();
        $this->assertSession()->pageTextNotContains('Request an update');
        $this->assertSession()->linkNotExistsExact('Update');

        // Set back the active status for the next iteration.
        $request->setRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_REQUESTED);
      }
      else {
        $this->assertSession()->pageTextNotContains('Request an update');
        $this->assertSession()->linkNotExistsExact('Update');
      }

      $node->delete();
    }
  }

  /**
   * Tests the creation of a new version for ongoing requests.
   */
  public function testOngoingCreateVersion(): void {
    // Create a node and its active, ongoing, translation.
    $node = $this->createBasicTestNode();
    $request = $this->createNodeTranslationRequest($node);
    $request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED);
    $request->save();

    $node->set('title', 'Basic translation node - update');
    $node->setNewRevision(TRUE);
    $node->save();

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');

    $this->assertSession()->pageTextContains('Request an update');
    $this->assertSession()->linkExistsExact('Update');
    $this->clickLink('Update');
    $this->assertSession()->addressEquals('/en/translation-request/epoetry/new-version-request/' . $request->id());
    $this->assertSession()->pageTextContains(sprintf('You are making a request for a new version. The previous version was translated with the %s request ID.', $request->getRequestId(TRUE)));

    // Click the Cancel button and assert we are back where we came from.
    $this->getSession()->getPage()->pressButton('Cancel');
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations/remote');

    $this->clickLink('Update');

    // Make some changes to the request: new language, request message,
    // different date.
    $this->getSession()->getPage()->checkField('Bulgarian');
    $this->getSession()->getPage()->checkField('German');
    $this->getSession()->getPage()->checkField('Italian');

    $this->getSession()->getPage()->fillField('deadline[0][value][date]', '10/10/2034');
    $this->getSession()->getPage()->fillField('Message', 'Some message to the provider');
    $contact_fields = [
      'Recipient' => 'test_recipient2',
      'Webmaster' => 'test_webmaster2',
      'Editor' => 'test_editor2',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }

    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');
    $this->assertSession()->pageTextContains(sprintf('The old request with the id %s has been marked as Finished.', $request->getRequestId(TRUE)));

    // We are back where we came from.
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations/remote');
    // We see still only one request, the new one, as the previous one was
    // marked as finished.
    $this->assertRequestStatusTable([
      'Requested',
      'ePoetry',
      'SenttoDGT',
      // The version got updated, not the number.
      'DIGIT/' . date('Y') . '/2000/1/0/TRA',
      'No',
      'No',
      '2034-Oct-10',
      // The mock link to Accept.
      'Accept',
    ]);

    // Assert the message.
    $this->assertSession()->pageTextContains('Some message to the provider');

    // We have the table of languages of this last request, all active but
    // none yet translated.
    $expected_languages = [];
    foreach (['bg', 'de', 'it'] as $langcode) {
      $expected_languages[$langcode] = [
        'langcode' => $langcode,
        'status' => 'Requested [in ePoetry]',
        'accepted_deadline' => 'N/A',
        'review' => FALSE,
      ];
    }
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Assert that the original request has been marked as finished.
    $request = TranslationRequestEpoetry::load($request->id());
    $this->assertEquals('Finished', $request->getRequestStatus());
    // The ePoetry status didn't get changed.
    $this->assertEquals('Accepted', $request->getEpoetryRequestStatus());

    // Assert that the XML request we built is correct.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(1, $requests);
    $xml = reset($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:createNewVersion><linguisticRequest><requestReference><dossier><requesterCode>DIGIT</requesterCode><number>2000</number><year>' . date('Y') . '</year></dossier><productType>TRA</productType><part>0</part></requestReference><requestDetails><title>A title prefix: A site ID - Basic translation node - update</title><internalReference>Translation request 2</internalReference><requestedDeadline>2034-10-10T23:59:00+02:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><comment>Some message to the provider. Page URL: http://web:8080/build/en/basic-translation-node-update</comment><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient2" contactRole="RECIPIENT"/><contact userId="test_webmaster2" contactRole="WEBMASTER"/><contact userId="test_editor2" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic-translation-node---update.html</fileName><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMiIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJFTiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDI8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0yIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzJdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTWwxYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlIC0gdXBkYXRlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2034-10-10T23:59:00+02:00" trackChanges="false"><language>BG</language></product><product requestedDeadline="2034-10-10T23:59:00+02:00" trackChanges="false"><language>DE</language></product><product requestedDeadline="2034-10-10T23:59:00+02:00" trackChanges="false"><language>IT</language></product></products></requestDetails></linguisticRequest><applicationName>digit</applicationName></ns1:createNewVersion></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

    // Assert that we got back a correct response and our request was correctly
    // updated.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $this->assertCount(1, $requests);
    $update_request = reset($requests);
    $this->assertNotEquals($request->id(), $update_request->id());
    // The old request got its status changed by this new update.
    $this->assertInstanceOf(TranslationRequestEpoetryInterface::class, $update_request);
    $this->assertEquals('en', $update_request->getSourceLanguageCode());
    $target_languages = $update_request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('bg', 'Requested'), $target_languages['bg']);
    $this->assertEquals(new LanguageWithStatus('de', 'Requested'), $target_languages['de']);
    $this->assertEquals(new LanguageWithStatus('it', 'Requested'), $target_languages['it']);
    $this->assertEquals('Requested', $update_request->getRequestStatus());
    $this->assertEquals('SenttoDGT', $update_request->getEpoetryRequestStatus());
    $this->assertEquals('epoetry', $update_request->getTranslatorProvider()->id());
    $this->assertFalse($update_request->isAutoAccept());
    $this->assertFalse($update_request->isAutoSync());
    $this->assertEquals('2034-10-10', $update_request->getDeadline()->format('Y-m-d'));
    $this->assertEquals('Some message to the provider', $update_request->getMessage());
    // The version got updated, not the number.
    $this->assertEquals('DIGIT/' . date('Y') . '/2000/1/0/TRA', $update_request->getRequestId(TRUE));
    $this->assertEquals([
      'Recipient' => 'test_recipient2',
      'Webmaster' => 'test_webmaster2',
      'Editor' => 'test_editor2',
    ], $update_request->getContacts());

    // Assert we see information about the request we replaced.
    $old_request_id = 'DIGIT/' . date('Y') . '/2000/0/0/TRA';
    $this->assertSession()->pageTextContains('Updated request');
    $this->assertSession()->pageTextContains('The current request was created as an update to a previous one (' . $old_request_id . ') which was still ongoing in ePoetry with at least 1 language. That request has been marked as Finished and was replaced with the current one.');
    $this->clickLink($old_request_id);
    // Assert that on the old request, we see the log message pointing to the
    // new one.
    $this->getSession()->getPage()->find('css', 'summary')->click();
    $this->assertSession()->pageTextContains('This request has been marked as Finished and has been replaced by an updated one: New request');
    // Go back to where we were.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');

    // Assert the logs.
    $expected_logs = [
      1 => [
        'Info',
        'The request has been sent successfully using the createNewVersionRequest request type.',
        $this->user->label(),
      ],
      2 => [
        'Info',
        'Message from ePoetry: We have received your createNewVersionRequest. We will process it soon.',
        $this->user->label(),
      ],
      3 => [
        'Info',
        'The request has replaced an ongoing translation request which has now been marked as Finished: ' . $old_request_id . '.',
        $this->user->label(),
      ],
    ];
    $this->assertLogMessagesTable($expected_logs);

    // Now test that we can make update requests to ongoing requests even if
    // we have already a translated request (not yet synced).
    // Start by translating directly this new request so we have a translated
    // request.
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EpoetryTranslationMockHelper::translateRequest($update_request, 'bg');
    EpoetryTranslationMockHelper::translateRequest($update_request, 'de');
    EpoetryTranslationMockHelper::translateRequest($update_request, 'it');

    $this->getSession()->reload();
    $this->assertRequestStatusTable([
      'Translated',
      'ePoetry',
      'SenttoDGT',
      'DIGIT/' . date('Y') . '/2000/1/0/TRA',
      'No',
      'No',
      '2034-Oct-10',
    ]);

    // Next, create a new request, accept it from DGT and then make an update
    // to this request.
    $this->getSession()->getPage()->checkField('French');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $this->getSession()->getPage()->fillField('Message', 'Message to the provider');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');
    $expected_ongoing_one = [
      'translator' => 'ePoetry',
      'status' => 'Translated',
      'title' => 'Basic translation node - update',
      'title_url' => $node->toUrl('revision')->toString(),
      'revision' => $node->getRevisionId(),
      'is_default' => 'Yes',
    ];
    $expected_ongoing_two = $expected_ongoing_one;
    // They are both for the same revision, except the second one is Requested.
    $expected_ongoing_two['status'] = 'Requested';
    $this->assertOngoingTranslations([
      $expected_ongoing_one,
      $expected_ongoing_two,
    ]);

    // Accept this request, make a new draft and make an update request.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE, [TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED]);
    $this->assertCount(1, $requests);
    $request = reset($requests);
    $this->getSession()->getPage()->find('xpath', "//tr[td//text()[contains(., 'Requested')]]")->clickLink('View');
    $this->assertSession()->linkNotExistsExact('Update');
    // Accept the request.
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EpoetryTranslationMockHelper::notifyRequest($request, [
      'type' => 'RequestStatusChange',
      'status' => 'Accepted',
    ]);
    // Keep track of the old revision ID.
    $revision_id = $node->getRevisionId();
    $node->set('title', 'Basic translation node - second update');
    $node->setNewRevision(TRUE);
    $node->save();
    $this->getSession()->reload();
    $this->assertSession()->linkExistsExact('Update');
    $this->clickLink('Update');
    $this->assertSession()->addressEquals('/en/translation-request/epoetry/new-version-request/' . $request->id());
    $this->assertSession()->pageTextContains(sprintf('You are making a request for a new version. The previous version was translated with the %s request ID.', $request->getRequestId(TRUE)));
    $this->getSession()->getPage()->checkField('Bulgarian');
    $this->getSession()->getPage()->fillField('deadline[0][value][date]', '10/10/2033');
    $this->getSession()->getPage()->fillField('Message', 'Another message to the provider');
    $contact_fields = [
      'Recipient' => 'test_recipient3',
      'Webmaster' => 'test_webmaster3',
      'Editor' => 'test_editor3',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }

    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');
    $this->assertSession()->pageTextContains(sprintf('The old request with the id %s has been marked as Finished.', $request->getRequestId(TRUE)));
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations/remote');
    // We still have the translated request and now a new, updated request for
    // the new content version.
    $revision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($revision_id);
    $expected_ongoing_one['is_default'] = 'No';
    $expected_ongoing_one['title_url'] = $revision->toUrl('revision')->toString();

    $expected_ongoing_two['title'] = 'Basic translation node - second update';
    $expected_ongoing_two['title_url'] = $node->toUrl()->toString();
    $expected_ongoing_two['revision'] = $node->getRevisionId();

    $this->assertOngoingTranslations([
      $expected_ongoing_one,
      $expected_ongoing_two,
    ]);
  }

  /**
   * Tests the access to modify an ongoing request, i.e. add languages.
   */
  public function testAccessOngoingModifyRequest(): void {
    $cases = [
      'sent to DGT request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_SENT,
        'visible' => FALSE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'accepted request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED,
        'visible' => TRUE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'accepted request with all languages requested' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED,
        'visible' => FALSE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'executed request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_EXECUTED,
        'visible' => TRUE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'suspended request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_SUSPENDED,
        'visible' => TRUE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'cancelled request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_CANCELLED,
        'visible' => FALSE,
        'ongoing' => FALSE,
        'finished' => TRUE,
      ],
    ];

    foreach ($cases as $case => $case_info) {
      // Create the node from scratch.
      $node = $this->createBasicTestNode();

      // Make an active request and set the case epoetry status.
      $request = $this->createNodeTranslationRequest($node);
      $request->setEpoetryRequestStatus($case_info['epoetry_status']);
      if ($case === 'accepted request with all languages requested') {
        // Add all the languages to the request.
        foreach (\Drupal::languageManager()->getLanguages() as $language) {
          if ($language->getId() === 'en') {
            continue;
          }

          $request->updateTargetLanguageStatus($language->getId(), TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REQUESTED);
        }
      }
      if ($case_info['finished']) {
        $request->setRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_FINISHED);
      }
      $request->save();

      $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
      $this->clickLink('Remote translations');

      if ($case_info['ongoing']) {
        $this->assertSession()->pageTextContains('Ongoing remote translation request via ePoetry');
      }
      else {
        $this->assertSession()->pageTextNotContains('Ongoing remote translation request via ePoetry');
      }

      if ($case_info['visible']) {
        $this->assertSession()->pageTextContains('Add new languages');
        $this->assertSession()->linkExistsExact('Add new languages');

        // If we are on the case where we should see the add languages button,
        // assert also that if the request is not active anymore, we don't
        // see it.
        $statuses = [
          TranslationRequestEpoetryInterface::STATUS_REQUEST_FINISHED,
          TranslationRequestEpoetryInterface::STATUS_REQUEST_FAILED,
          TranslationRequestEpoetryInterface::STATUS_REQUEST_FAILED_FINISHED,
        ];
        foreach ($statuses as $status) {
          $request->setRequestStatus($status);
          $request->save();

          $this->getSession()->reload();
          $this->assertSession()->pageTextNotContains('Add new languages');
          $this->assertSession()->linkNotExistsExact('Add new languages');
        }

        // Set back the active status for the next iteration.
        $request->setRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_REQUESTED);
      }
      else {
        $this->assertSession()->pageTextNotContains('Add new languages');
        $this->assertSession()->linkNotExistsExact('Add new languages');
      }

      $node->delete();
    }
  }

  /**
   * Tests that we can add new languages to an Accepted request.
   */
  public function testModifyLinguisticRequest(): void {
    // Create a node and its active, ongoing, translation.
    $node = $this->createBasicTestNode();
    $request = $this->createNodeTranslationRequest($node);
    $request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED);
    $request->save();

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');

    // Assert our Accepted request status.
    $this->assertRequestStatusTable([
      'Requested',
      'ePoetry',
      'Accepted',
      'DIGIT/' . date('Y') . '/2000/0/0/TRA',
      'No',
      'No',
      '2035-Oct-10',
      'Cancel',
    ]);

    // We have a single language.
    $expected_languages = [];
    foreach (['fr'] as $langcode) {
      $expected_languages[$langcode] = [
        'langcode' => $langcode,
        'status' => 'Requested [in ePoetry]',
        'accepted_deadline' => 'N/A',
        'review' => FALSE,
      ];
    }
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Assert, though, that we can add new languages.
    $this->assertSession()->pageTextContains('Add new languages');
    $this->assertSession()->linkExistsExact('Add new languages');
    $this->clickLink('Add new languages');
    $this->assertSession()->addressEquals('/en/translation-request/epoetry/modify-linguistic-request/' . $request->id());
    $this->assertSession()->pageTextContains(sprintf('You are making a request to add extra languages to an existing, ongoing request with the ID %s.', $request->getRequestId(TRUE)));

    // Click the Cancel button and assert we are back where we came from.
    $this->getSession()->getPage()->pressButton('Cancel');
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations/remote');

    $this->clickLink('Add new languages');

    // Assert that FR is disabled and checked because the ongoing request
    // is for FR.
    $this->assertSession()->fieldDisabled('French');
    $this->assertSession()->checkboxChecked('French');

    // Submit the form without selecting any other languages and assert we have
    // a validation in place.
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('Please select at least one extra language.');

    // Add another language and submit the request.
    $this->getSession()->getPage()->checkField('German');
    $this->getSession()->getPage()->pressButton('Save and send');

    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    // We are back where we came from.
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations/remote');
    // Our request should stay unchanged, apart from the new language addition.
    $this->assertRequestStatusTable([
      'Requested',
      'ePoetry',
      'Accepted',
      'DIGIT/' . date('Y') . '/2000/0/0/TRA',
      'No',
      'No',
      '2035-Oct-10',
      'Cancel',
    ]);

    // We now have 2 translations.
    $expected_languages = [];
    foreach (['fr', 'de'] as $langcode) {
      $expected_languages[$langcode] = [
        'langcode' => $langcode,
        'status' => 'Requested [in ePoetry]',
        'accepted_deadline' => 'N/A',
        'review' => FALSE,
      ];
    }
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Assert we only have 1 request and we have no change on it except the
    // language.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, FALSE);
    $this->assertCount(1, $requests);
    $request = reset($requests);
    $this->assertInstanceOf(TranslationRequestEpoetryInterface::class, $request);
    $this->assertEquals('en', $request->getSourceLanguageCode());
    $target_languages = $request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('fr', 'Requested'), $target_languages['fr']);
    $this->assertEquals(new LanguageWithStatus('de', 'Requested'), $target_languages['de']);
    $this->assertCount(2, $target_languages);
    $this->assertEquals('Requested', $request->getRequestStatus());
    $this->assertEquals('Accepted', $request->getEpoetryRequestStatus());
    $this->assertEquals('epoetry', $request->getTranslatorProvider()->id());
    $this->assertNull($request->getMessage());
    $this->assertFalse($request->isAutoAccept());
    $this->assertFalse($request->isAutoSync());
    $this->assertEquals('2035-10-10', $request->getDeadline()->format('Y-m-d'));
    // The version got updated, not the number.
    $this->assertEquals('DIGIT/' . date('Y') . '/2000/0/0/TRA', $request->getRequestId(TRUE));
    $this->assertEquals([
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ], $request->getContacts());

    // Assert that the XML request we built is correct.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(1, $requests);
    $xml = reset($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:modifyLinguisticRequest><modifyLinguisticRequest><requestReference><dossier><requesterCode>DIGIT</requesterCode><number>2000</number><year>' . date('Y') . '</year></dossier><productType>TRA</productType><part>0</part></requestReference><requestDetails><contacts xsi:type="ns1:contacts"><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><products xsi:type="ns1:products"><product requestedDeadline="2035-10-10T23:59:00+02:00" trackChanges="false"><language>DE</language></product></products></requestDetails></modifyLinguisticRequest><applicationName>digit</applicationName></ns1:modifyLinguisticRequest></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

    // Assert the logs. This set of logs is missing the initial ones because
    // we created the request programatically.
    $expected_logs = [
      1 => [
        'Info',
        'The modifyLinguisticRequest has been sent to ePoetry for adding the following extra languages: German.',
        $this->user->label(),
      ],
      2 => [
        'Info',
        'Message from ePoetry: We have received your modifyLinguisticRequest. We will process it soon.',
        $this->user->label(),
      ],
    ];
    $this->assertLogMessagesTable($expected_logs);
  }

  /**
   * Tests the failure of the modifyLinguisticRequest.
   *
   * It asserts that if the request fails, the translation request entity
   * doesn't get updated with any other languages.
   */
  public function testFailedModifyLinguisticRequest(): void {
    // Create a node with an ongoing, accepted request.
    $node = $this->createBasicTestNode();
    $request = $this->createNodeTranslationRequest($node);
    $request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED);
    $request->save();

    // Tell the mock to fail this request.
    \Drupal::state()->set('oe_translation_epoetry_mock_response_error', [
      'code' => 'ns0:Server',
      'string' => 'There was an error in your request.',
    ]);

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');

    $this->assertSession()->linkExistsExact('Add new languages');
    $this->clickLink('Add new languages');
    // Add German as an extra language.
    $this->getSession()->getPage()->checkField('German');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('There was a problem sending the request to ePoetry.');

    // Assert that the request still only has a single language (the original
    // one).
    $request = TranslationRequestEpoetry::load($request->id());
    $this->assertCount(1, $request->getTargetLanguages());
    $this->assertEquals('fr', $request->getTargetLanguage('fr')->getLangcode());

    $expected_logs = [
      1 => [
        'Error',
        'There was an error with the modifyLinguisticRequest for adding the following extra languages: German. The error: There was an error in your request.',
        $this->user->label(),
      ],
    ];
    $this->assertLogMessagesTable($expected_logs);
  }

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
    $logs = \Drupal::service('oe_translation_epoetry_mock.logger.mock_logger')->getLogs();
    // The last two logs should show the missing request: the last one is our
    // response to ePoetry and the one before last is us logging that we are
    // missing the request.
    $log = array_pop($logs);
    $this->assertEquals(RfcLogLevel::INFO, $log['level']);
    $log = array_pop($logs);
    $this->assertEquals(RfcLogLevel::ERROR, $log['level']);
    $this->assertEquals('The ePoetry notification could not find a translation request for the reference: <strong>@reference</strong>.', $log['message']);
    $this->assertEquals('DIGIT/' . date('Y') . '/2000/0/0/TRA', $log['context']['@reference']);
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
    $logs = \Drupal::service('oe_translation_epoetry_mock.logger.mock_logger')->getLogs();
    // The last two logs should show the missing request: the last one is our
    // response to ePoetry and the one before last is us logging that we are
    // missing the request.
    $log = array_pop($logs);
    $this->assertEquals(RfcLogLevel::INFO, $log['level']);
    $log = array_pop($logs);
    $this->assertEquals(RfcLogLevel::ERROR, $log['level']);
    $this->assertEquals('The ePoetry notification could not find a translation request for the reference: <strong>@reference</strong>.', $log['message']);
    $this->assertEquals('DIGIT/' . date('Y') . '/2000/0/0/TRA', $log['context']['@reference']);
    \Drupal::service('oe_translation_epoetry_mock.logger.mock_logger')->clearLogs();
    EpoetryTranslationMockHelper::translateRequest($request, 'fr');
    $logs = \Drupal::service('oe_translation_epoetry_mock.logger.mock_logger')->getLogs();
    $log = array_pop($logs);
    $this->assertEquals(RfcLogLevel::INFO, $log['level']);
    $log = array_pop($logs);
    $this->assertEquals(RfcLogLevel::ERROR, $log['level']);
    $this->assertEquals('The ePoetry notification could not find a translation request for the reference: <strong>@reference</strong>.', $log['message']);
    $this->assertEquals('DIGIT/' . date('Y') . '/2000/0/0/TRA', $log['context']['@reference']);
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

    $storage->resetCache();
    $request = $storage->load($request->id());
    $this->assertEquals('Accepted', $request->getTargetLanguage('fr')->getStatus());
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
   * Tests resubmitting rejected requests.
   */
  public function testResubmitRejectedRequests(): void {
    $request_storage = \Drupal::entityTypeManager()->getStorage('oe_translation_request');
    $node = $this->createBasicTestNode();
    // Create a rejected request, just so we have one in the system for the
    // node.
    $request = $this->createNodeTranslationRequest($node, TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED, [
      [
        'langcode' => 'fr',
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REQUESTED,
      ],
    ]);
    $request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_REJECTED);
    $request->save();

    // Now create a new request for this node that is active. Note that because
    // the previous request was rejected, the request ID can stay identical
    // as this would be technically a resubmit request.
    $request = $this->createNodeTranslationRequest($node, TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED, [
      [
        'langcode' => 'fr',
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REQUESTED,
      ],
    ]);
    $request->save();

    // Go to the remote translation dashboard and assert that while we have an
    // active request, we cannot make a new one, as expected under the normal
    // flow.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->assertRequestStatusTable([
      'Requested',
      'ePoetry',
      'SenttoDGT',
      'DIGIT/' . date('Y') . '/2000/0/0/TRA',
      'No',
      'No',
      '2035-Oct-10',
      // The mock link to accept the request.
      'Accept',
    ]);
    $this->assertSession()->fieldDisabled('Translator');
    // We also don't see any message of a rejected request.
    $this->assertSession()->pageTextNotContains('had been rejected');

    // Reject the request.
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EpoetryTranslationMockHelper::notifyRequest($request, [
      'type' => 'RequestStatusChange',
      'status' => 'Rejected',
      'message' => 'Please fix your content',
    ]);
    $request_storage->resetCache();
    /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
    $request = $request_storage->load($request->id());
    $this->assertEquals(TranslationRequestEpoetryInterface::STATUS_REQUEST_REJECTED, $request->getEpoetryRequestStatus());
    $this->assertEquals(TranslationRequestEpoetryInterface::STATUS_REQUEST_FINISHED, $request->getRequestStatus());

    // Assert the rejected request has logged the information.
    $this->assertLogMessagesValues($request, [
      // It doesn't contain the initial logs as we created the request manually.
      1 => [
        'Warning',
        'The request has been Rejected by ePoetry. Planning agent: test. Planning sector: DGT. Message: Please fix your content.',
        'Anonymous',
      ],
    ]);

    // Go to the remote translation dashboard and assert there are no requests
    // visible, we can make a new translation, but we also have a message
    // stating that we had a rejected request.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->assertNull($this->getSession()->getPage()->find('css', 'table.request-status-meta-table'));
    $this->assertSession()->pageTextContains('The last ePoetry translation request with the ID DIGIT/' . date('Y') . '/2000/0/0/TRA had been rejected. You can resubmit to correct it.');
    $this->assertSession()->fieldEnabled('Translator');

    // Make a new request to "correct" the reason why it was rejected. This can
    // be done even without making a change to the content.
    $this->assertSession()->pageTextContains('New translation request using ePoetry');
    $this->assertSession()->pageTextContains('You are making a request for a new version. The previous version was translated with the DIGIT/' . date('Y') . '/2000/0/0/TRA request ID. The previous request had been rejected. You are now resubmitting the request, please ensure it is now valid.');
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

    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to ePoetry.');

    // Assert that the XML request we built is correct.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(1, $requests);
    $xml = reset($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:resubmitRequest><resubmitRequest><requestReference><dossier><requesterCode>DIGIT</requesterCode><number>2000</number><year>' . date('Y') . '</year></dossier><productType>TRA</productType><part>0</part></requestReference><requestDetails><title>A title prefix: A site ID - Basic translation node</title><internalReference>Translation request 3</internalReference><requestedDeadline>2032-10-10T23:59:00+02:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><comment>Page URL: http://web:8080/build/en/basic-translation-node</comment><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic-translation-node.html</fileName><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMyIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJFTiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDM8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0zIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzNdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTTExYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2032-10-10T23:59:00+02:00" trackChanges="false"><language>FR</language></product></products></requestDetails></resubmitRequest><applicationName>digit</applicationName><templateName>WEBTRA</templateName></ns1:resubmitRequest></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

    // Assert that we got back a correct response and our request was correctly
    // updated.
    $requests = TranslationRequest::loadMultiple();
    // We have 3 requests in total: the first rejected, the second active which
    // was rejected and the third which is active now.
    $this->assertCount(3, $requests);
    $request = end($requests);
    $this->assertInstanceOf(TranslationRequestEpoetryInterface::class, $request);
    $this->assertEquals('en', $request->getSourceLanguageCode());
    $target_languages = $request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('fr', 'Requested'), $target_languages['fr']);
    $this->assertEquals('Requested', $request->getRequestStatus());
    $this->assertEquals('SenttoDGT', $request->getEpoetryRequestStatus());
    $this->assertEquals('epoetry', $request->getTranslatorProvider()->id());
    $this->assertFalse($request->isAutoAccept());
    $this->assertFalse($request->isAutoSync());
    $this->assertEquals('2032-10-10', $request->getDeadline()->format('Y-m-d'));
    $this->assertEquals('DIGIT/' . date('Y') . '/2000/0/0/TRA', $request->getRequestId(TRUE));
    $this->assertEquals([
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ], $request->getContacts());

    // Assert the request status table.
    $this->assertRequestStatusTable([
      'Requested',
      'ePoetry',
      'SenttoDGT',
      // The ID is the same as the previous was rejected.
      'DIGIT/' . date('Y') . '/2000/0/0/TRA',
      'No',
      'No',
      '2032-Oct-10',
      // The mock link to accept the request.
      'Accept',
    ]);

    $expected_logs = [
      1 => [
        'Info',
        'The request has been sent successfully using the resubmitRequest request type.',
        $this->user->label(),
      ],
      2 => [
        'Info',
        'Message from ePoetry: We have received your resubmitRequest. We will process it soon.',
        $this->user->label(),
      ],
    ];
    $this->assertLogMessagesTable($expected_logs);
  }

  /**
   * Tests the case when a request crashes or fails.
   */
  public function testFailedRequest(): void {
    // Create a node and mimic it having been translated via ePoetry. This is
    // so that we have a request already in the system that went through and we
    // can run assertions about messaging and stuff about past requests.
    $node = $this->createBasicTestNode();
    $node->addTranslation('fr', ['title' => 'Basic translation node fr'] + $node->toArray());
    $node->setNewRevision(FALSE);
    $node->save();
    $request = $this->createNodeTranslationRequest($node, TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED, [
      [
        'langcode' => 'fr',
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SYNCHRONISED,
      ],
    ]);
    $request->save();

    // Make a new draft of the node and start a new translation.
    $node->set('title', 'Basic translation node - update');
    $node->setNewRevision(TRUE);
    $node->save();

    // Create a node and make an ePoetry request that fails.
    \Drupal::state()->set('oe_translation_epoetry_mock_response_error', [
      'code' => 'ns0:Server',
      'string' => 'There was an error in your request.',
    ]);
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->assertSession()->pageTextContains(sprintf('You are making a request for a new version. The previous version was translated with the %s request ID.', $request->getRequestId(TRUE)));
    $this->getSession()->getPage()->checkField('Bulgarian');
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

    $this->assertSession()->pageTextContains('There was a problem sending the request to ePoetry.');

    // We have a failed request so no new translations can be made until the
    // user dispenses with this request by marking it as finished.
    $this->assertSession()->fieldDisabled('Translator');

    $this->assertRequestStatusTable([
      'Failed',
      'ePoetry',
      // No epoetry status.
      '',
      // No ID.
      '',
      'No',
      'No',
      '2032-Oct-10',
    ]);

    $expected_logs = [
      1 => [
        'Error',
        'Phpro\SoapClient\Exception\SoapException: There was an error in your request.',
        $this->user->label(),
      ],
    ];
    $this->assertLogMessagesTable($expected_logs);

    // Go to the dashboard and mark the request as Failed & Finished.
    $this->clickLink('Dashboard');
    $this->assertSession()->pageTextNotContains('There are no ongoing remote translation requests');
    $expected_ongoing = [
      'translator' => 'ePoetry',
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
    $requests = TranslationRequest::loadMultiple();
    $this->assertCount(2, $requests);
    $request = end($requests);
    $this->assertEquals(TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED_FINISHED, $request->getRequestStatus());

    // Go back to and create a request, this time going through.
    \Drupal::state()->delete('oe_translation_epoetry_mock_response_error');
    $this->clickLink('Remote translations');
    $request = reset($requests);
    $this->assertSession()->pageTextContains(sprintf('You are making a request for a new version. The previous version was translated with the %s request ID.', $request->getRequestId(TRUE)));
    $this->getSession()->getPage()->checkField('Bulgarian');
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

    // Assert that we got back a correct response and our request was correctly
    // updated.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    // We can only find one ongoing request.
    $this->assertCount(1, $requests);
    $request = reset($requests);
    $this->assertInstanceOf(TranslationRequestEpoetryInterface::class, $request);
    $this->assertEquals('en', $request->getSourceLanguageCode());
    $target_languages = $request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('bg', 'Requested'), $target_languages['bg']);
    $this->assertEquals('Requested', $request->getRequestStatus());
    $this->assertEquals('SenttoDGT', $request->getEpoetryRequestStatus());
    $this->assertEquals('epoetry', $request->getTranslatorProvider()->id());
    $this->assertFalse($request->isAutoAccept());
    $this->assertFalse($request->isAutoSync());
    $this->assertEquals('2032-10-10', $request->getDeadline()->format('Y-m-d'));
    $this->assertEquals('Message to the provider', $request->getMessage());
    $this->assertEquals('DIGIT/' . date('Y') . '/2000/1/0/TRA', $request->getRequestId(TRUE));
    $this->assertEquals([
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ], $request->getContacts());

    // Assert the request status table.
    $this->assertRequestStatusTable([
      'Requested',
      'ePoetry',
      'SenttoDGT',
      'DIGIT/' . date('Y') . '/2000/1/0/TRA',
      'No',
      'No',
      '2032-Oct-10',

     // The mock link tu accept the request.
      'Accept',
    ]);
  }

  /**
   * Tests the resetting of the current dossier to force a new dossier creation.
   */
  public function testResetDossier(): void {
    // Create a node and make a new translation request for it. This will
    // start a dossier.
    $node = $this->createBasicTestNode();
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->getSession()->getPage()->checkField('Bulgarian');
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

    // Accept and translate.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $request = reset($requests);
    EpoetryTranslationMockHelper::$databasePrefix = $this->databasePrefix;
    EpoetryTranslationMockHelper::notifyRequest($request, [
      'type' => 'RequestStatusChange',
      'status' => 'Accepted',
    ]);
    EpoetryTranslationMockHelper::notifyRequest($request, [
      'type' => 'ProductStatusChange',
      'status' => 'Accepted',
      'language' => 'bg',
    ]);
    EpoetryTranslationMockHelper::translateRequest($request, 'bg');

    // Assert the created dossier.
    $dossiers = RequestFactory::getEpoetryDossiers();
    $this->assertEquals([
      1001 => [
        'part' => 0,
        'code' => 'DIGIT',
        'year' => date('Y'),
      ],
    ], $dossiers);

    // Log in as an admin and go reset the dossier.
    $user = $this->drupalCreateUser([
      'administer remote translators',
      'access administration pages',
      'access toolbar',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('/admin/structure/remote-translation-provider/epoetry/edit');
    $this->assertSession()->pageTextContains('[CURRENT] Number 1001 / Code DIGIT / Year ' . date('Y') . ' / Part 0');
    $this->assertSession()->pageTextNotContains('SET TO RESET');
    $this->getSession()->getPage()->checkField('Reset current dossier');
    $this->getSession()->getPage()->pressButton('Save');
    $this->drupalGet('/admin/structure/remote-translation-provider/epoetry/edit');
    $this->assertSession()->pageTextContains('[CURRENT] Number 1001 / Code DIGIT / Year ' . date('Y') . ' / Part 0 [SET TO RESET]');

    // Now create a new request and assert the dossier has been reset and a
    // createLinguisticRequest request is made.
    $user = $this->setUpTranslatorUser();
    $this->drupalLogin($user);
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->getSession()->getPage()->checkField('Bulgarian');
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

    $dossiers = RequestFactory::getEpoetryDossiers();
    $this->assertEquals([
      1001 => [
        'part' => 0,
        'code' => 'DIGIT',
        'year' => date('Y'),
        'reset' => TRUE,
      ],
      1002 => [
        'part' => 0,
        'code' => 'DIGIT',
        'year' => date('Y'),
      ],
    ], $dossiers);
  }

  /**
   * Tests that by default, we use the proper authentication service.
   */
  public function testAuthentication(): void {
    \Drupal::state()->set('oe_translation_epoetry_mock.bypass_mock_authentication', TRUE);

    $node = $this->createBasicTestNode();
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->getSession()->getPage()->checkField('Bulgarian');
    $this->getSession()->getPage()->fillField('translator_configuration[epoetry][deadline][0][value][date]', '10/10/2032');
    $contact_fields = [
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ];
    foreach ($contact_fields as $field => $value) {
      $this->getSession()->getPage()->fillField($field, $value);
    }

    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('There was a problem sending the request to ePoetry.');
    $this->getSession()->getPage()->find('css', 'summary')->click();
    $this->assertSession()->pageTextContains('Phpro\SoapClient\Exception\SoapException: Client certificate authentication failed due to the following error');
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
  }

  /**
   * Tests that the available languages for ePoetry can be altered.
   */
  public function testLanguagesAlter(): void {
    $node = $this->createBasicTestNode();
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->assertSession()->pageTextContains('New translation request using ePoetry');

    $this->assertSession()->fieldExists('Bulgarian');
    $this->assertSession()->fieldExists('French');

    \Drupal::state()->set('oe_translation_test.remove_languages', ['bg']);

    $this->getSession()->reload();
    $this->assertSession()->pageTextContains('New translation request using ePoetry');

    $this->assertSession()->fieldNotExists('Bulgarian');
    $this->assertSession()->fieldExists('French');

    // Create an accepted request in FR for this node and try to add a new
    // language to assert we have the event subscriber firing there as well.
    $request = $this->createNodeTranslationRequest($node);
    $request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED);
    $request->save();

    $this->getSession()->reload();
    $this->clickLink('Add new languages');
    $this->assertSession()->fieldDisabled('French');
    $this->assertSession()->checkboxChecked('French');
    $this->assertSession()->fieldNotExists('Bulgarian');
  }

  /**
   * Tests the ePoetry translation dashboard.
   */
  public function testEpoetryTranslationDashboard(): void {
    $request_id = [
      'code' => 'DIGIT',
      'year' => date('Y'),
      'number' => 2000,
      'part' => 0,
      'version' => 0,
      'service' => 'TRA',
    ];

    // Create a node with a rejected translation.
    $first_node = $this->createBasicTestNode();
    $first_node->set('title', 'First node');
    $first_node->save();
    $request = $this->createNodeTranslationRequest($first_node, TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED, [
      [
        'langcode' => 'fr',
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REQUESTED,
      ],
      [
        'langcode' => 'de',
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REQUESTED,
      ],
    ]);
    $request->setRequestId($request_id);
    $request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_REJECTED);
    $request->save();

    // Create a node with an ongoing translation.
    $second_node = $this->createBasicTestNode();
    $second_node->set('title', 'Second node');
    $second_node->save();
    $request = $this->createNodeTranslationRequest($second_node, TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED, [
      [
        'langcode' => 'it',
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_ONGOING,
      ],
    ]);
    $request_id['part'] = 1;
    $request->setRequestId($request_id);
    $request->save();
    // Make a new revision to this node.
    $second_node->set('title', 'Second node - updated');
    $second_node->setNewRevision(TRUE);
    $second_node->save();

    // Create another node and failed request.
    $third_node = $this->createBasicTestNode();
    $third_node->set('title', 'Third node');
    $third_node->save();
    $request = $this->createNodeTranslationRequest($third_node, TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED, [
      [
        'langcode' => 'ro',
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REQUESTED,
      ],
    ]);
    $request_id['part'] = 2;
    $request->setRequestId($request_id);
    $request->save();

    // Assert the dashboard only accessible to users with the correct
    // permission.
    $this->drupalLogout();
    $this->drupalGet('admin/content/epoetry-translation-requests');
    $this->assertSession()->pageTextContains('Access denied');

    $user = $this->createUser();
    $this->drupalLogin($user);
    $this->drupalGet('admin/content/epoetry-translation-requests');
    $this->assertSession()->pageTextContains('Access denied');

    $user->addRole('oe_translator');
    $user->save();
    $this->getSession()->reload();
    $this->assertSession()->pageTextContains('ePoetry translation requests');

    // Assert we can see all the nodes and the rejected and failed rows have
    // color classes.
    $this->assertSession()->linkExistsExact('Third node');
    // The second node title is that of the first revision (for which the
    // request has been made).
    $this->assertSession()->linkExistsExact('Second node');
    $this->assertSession()->linkExistsExact('First node');

    $this->assertTrue($this->getSession()->getPage()->find('xpath', "//table/tbody/tr[td//text()[contains(., 'First node')]]")->hasClass('color-warning'));
    $this->assertTrue($this->getSession()->getPage()->find('xpath', "//table/tbody/tr[td//text()[contains(., 'Third node')]]")->hasClass('color-error'));

    // Assert the filters.
    $this->getSession()->getPage()->selectFieldOption('Request status', 'Failed');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->assertSession()->linkExistsExact('Third node');
    $this->assertSession()->linkNotExistsExact('Second node');
    $this->assertSession()->linkNotExistsExact('First node');
    $this->getSession()->getPage()->pressButton('Reset');

    $this->getSession()->getPage()->selectFieldOption('ePoetry status', 'SenttoDGT');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->assertSession()->linkNotExistsExact('Third node');
    $this->assertSession()->linkExistsExact('Second node');
    $this->assertSession()->linkNotExistsExact('First node');
    $this->getSession()->getPage()->pressButton('Reset');

    $this->getSession()->getPage()->selectFieldOption('Requested language', 'French');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->assertSession()->linkExistsExact('First node');
    $this->assertSession()->linkNotExistsExact('Second node');
    $this->assertSession()->linkNotExistsExact('Third node');
    $this->getSession()->getPage()->pressButton('Reset');

    $this->getSession()->getPage()->fillField('Node ID', $second_node->id());
    $this->getSession()->getPage()->pressButton('Apply');
    $this->assertSession()->linkNotExistsExact('First node');
    $this->assertSession()->linkExistsExact('Second node');
    $this->assertSession()->linkNotExistsExact('Third node');
    $this->getSession()->getPage()->pressButton('Reset');

    $this->getSession()->getPage()->fillField('Request ID', 'DIGIT/2023/2000/0/0/TRA');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->assertSession()->linkNotExistsExact('Third node');
    $this->assertSession()->linkNotExistsExact('Second node');
    $this->assertSession()->linkExistsExact('First node');
    $this->getSession()->getPage()->pressButton('Reset');

    $this->getSession()->getPage()->fillField('Content', 'Second');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->assertSession()->linkNotExistsExact('Third node');
    $this->assertSession()->linkExistsExact('Second node');
    $this->assertSession()->linkNotExistsExact('First node');
    $this->getSession()->getPage()->pressButton('Reset');

    // Assert the tooltip.
    $this->assertEquals('2 / 0 ⓘ', $this->getSession()->getPage()->find('xpath', "//table/tbody/tr[td//text()[contains(., 'First node')]]/td[5]")->getText());
    $this->assertEquals('Requested [in ePoetry]: French, German', strip_tags($this->getSession()->getPage()->find('xpath', "//table/tbody/tr[td//text()[contains(., 'First node')]]/td[5]")->find('css', '.oe-translation-tooltip--text')->getHtml()));
    $this->assertEquals('1 / 0 ⓘ', $this->getSession()->getPage()->find('xpath', "//table/tbody/tr[td//text()[contains(., 'Second node')]]/td[5]")->getText());
    $this->assertEquals('Ongoing [in ePoetry]: Italian', strip_tags($this->getSession()->getPage()->find('xpath', "//table/tbody/tr[td//text()[contains(., 'Second node')]]/td[5]")->find('css', '.oe-translation-tooltip--text')->getHtml()));
    $this->assertEquals('1 / 0 ⓘ', $this->getSession()->getPage()->find('xpath', "//table/tbody/tr[td//text()[contains(., 'Third node')]]/td[5]")->getText());
    $this->assertEquals('Requested [in ePoetry]: Romanian', strip_tags($this->getSession()->getPage()->find('xpath', "//table/tbody/tr[td//text()[contains(., 'Third node')]]/td[5]")->find('css', '.oe-translation-tooltip--text')->getHtml()));
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
    $logs = \Drupal::service('oe_translation_epoetry_mock.logger.mock_logger')->getLogs();
    foreach ($logs as $log) {
      $this->assertStringNotContainsString('The mock ticket validation kicked in.', $log['message']);
    }

    \Drupal::service('oe_translation_epoetry_mock.logger.mock_logger')->clearLogs();

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
    $logs = \Drupal::service('oe_translation_epoetry_mock.logger.mock_logger')->getLogs();
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
      // The request status has been marked as Finished.
      $this->assertEquals($expected_status, $request->getRequestStatus());
      $part++;
    }
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
