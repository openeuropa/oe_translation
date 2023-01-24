<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_epoetry\FunctionalJavascript;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\NodeInterface;
use Drupal\oe_translation\LanguageWithStatus;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetry;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\oe_translation_epoetry_mock\EpoetryTranslationMockHelper;
use Drupal\oe_translation_remote\Entity\RemoteTranslatorProvider;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Drupal\Tests\oe_translation\FunctionalJavascript\TranslationTestBase;
use Drupal\Tests\oe_translation\Traits\TranslationsTestTrait;
use Drupal\Tests\oe_translation_remote\Traits\RemoteTranslationsTestTrait;

/**
 * Tests the remote translations via ePoetry.
 */
class EpoetryTranslationTest extends TranslationTestBase {

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
    // @todo maybe we can remote the remote_test module.
    'oe_translation_remote_test',
    'oe_translation_epoetry',
    'oe_translation_epoetry_mock',
  ];

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
    $provider->setProviderConfiguration($configuration);
    $provider->save();

    $user = $this->setUpTranslatorUser();
    $this->drupalLogin($user);
  }

  /**
   * Tests the ePoetry translation provider configuration form.
   */
  public function testEpoetryProviderConfiguration(): void {
    $user = $this->drupalCreateUser([
      'administer site configuration',
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

    // Assert we have the contact form elements.
    $contact_fields = [
      'Author',
      'Requester',
      'Recipient',
      'Editor',
      'Webmaster',
    ];
    foreach ($contact_fields as $field) {
      $this->assertSession()->fieldExists($field);
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
    $this->getSession()->getPage()->pressButton('Save');
    $this->getSession()->getPage()->fillField('Machine-readable name', 'epoetry_provider');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Created the ePoetry provider Remote Translator Provider.');

    // Make sure the configuration is saved properly.
    $translator = \Drupal::entityTypeManager()->getStorage('remote_translation_provider')->load('epoetry_provider');
    $this->assertEquals('ePoetry provider', $translator->label());
    $this->assertEquals('epoetry', $translator->getProviderPlugin());
    $this->assertEquals([
      'contacts' => [
        'Recipient' => 'test_recipient',
        'Webmaster' => 'test_webmaster',
        'Editor' => 'test_editor',
      ],
      'auto_accept' => TRUE,
      'title_prefix' => 'The title prefix',
      'site_id' => 'The site ID',
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
    ], $translator->getProviderConfiguration());
  }

  /**
   * Tests that some configuration is pre-set from the provider configuration.
   */
  public function testEpoetryPresetTranslationForm(): void {
    $node = $this->createBasicTestNode();
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->getSession()->getPage()->selectFieldOption('Translator', 'ePoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('New translation request using ePoetry');

    // The contacts fields are empty and the auto-accept checkbox is enabled
    // because the provider is not yet configured.
    $this->assertSession()->fieldEnabled('Auto accept translations');
    $this->assertSession()->elementTextEquals('css', '.form-item-translator-configuration-epoetry-auto-accept-value .description', 'Choose if incoming translations should be auto-accepted');
    $this->assertSession()->fieldExists('Automatic synchronisation');
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
    $this->getSession()->getPage()->selectFieldOption('Translator', 'ePoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
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
    $node = $this->createBasicTestNode();
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');

    // Select the ePoetry translator.
    $select = $this->assertSession()->selectExists('Translator');
    $select->selectOption('epoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
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
    $this->getSession()->getPage()->checkField('French');

    // Assert the message length is under 1700 characters.
    $this->getSession()->getPage()->fillField('Message', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cup');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('Please keep the message under 1700 characters.');

    // Put a smaller message and send.
    $this->getSession()->getPage()->fillField('Message', 'Message to the provider');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent to DGT.');

    // Assert that the XML request we built is correct.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(1, $requests);
    $xml = reset($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:createLinguisticRequest><requestDetails><title>A title prefix: A site ID - Basic translation node</title><internalReference>Translation request 1</internalReference><requestedDeadline>2032-10-10T12:00:00+00:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><comment>Message to the provider</comment><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic translation node</fileName><comment>http://web:8080/build/en/basic-translation-node</comment><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMSIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJlbiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDE8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0xIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzFdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTVYxYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2032-10-10T12:00:00+00:00" trackChanges="false"><language>bg</language></product><product requestedDeadline="2032-10-10T12:00:00+00:00" trackChanges="false"><language>fr</language></product></products></requestDetails><applicationName>digit</applicationName><templateName>WEBTRA</templateName></ns1:createLinguisticRequest></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

    // Assert that we got back a correct response and our request was correctly
    // updated.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $this->assertCount(1, $requests);
    $request = reset($requests);
    $this->assertInstanceOf(TranslationRequestEpoetryInterface::class, $request);
    $this->assertEquals('en', $request->getSourceLanguageCode());
    $target_languages = $request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('bg', 'Active'), $target_languages['bg']);
    $this->assertEquals(new LanguageWithStatus('fr', 'Active'), $target_languages['fr']);
    $this->assertEquals('Active', $request->getRequestStatus());
    $this->assertEquals('SenttoDGT', $request->getEpoetryRequestStatus());
    $this->assertEquals('epoetry', $request->getTranslatorProvider()->id());
    $this->assertNull($request->getAcceptedDeadline());
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
      'Active',
      'ePoetry',
      'SenttoDGT',
      'DIGIT/' . date('Y') . '/1001/0/0/TRA',
      'No',
      'No',
      '2032-Oct-10',
      'N/A',
      // The mock link tu accept the request.
      'Accept',
    ]);

    // Assert the message.
    $this->assertSession()->pageTextContains('Message to provider');

    // We have the table of languages of this last request, all active but
    // none yet translated.
    $expected_languages = [];
    foreach (['bg', 'fr'] as $langcode) {
      $expected_languages[$langcode] = [
        'langcode' => $langcode,
        'status' => 'Active',
        'review' => FALSE,
      ];
    }
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);

    // Assert that we are storing the generated dossier in the state.
    $dossiers = \Drupal::state()->get('oe_translation_epoetry.dossiers');
    $this->assertEquals([
      1001 => [
        'part' => 0,
        'code' => 'DIGIT',
        'year' => date('Y'),
      ],
    ], $dossiers);
  }

  /**
   * Tests the that we are creating new parts when we translate new nodes.
   *
   * This tests that when we translate nodes, we add parts to the same dossier
   * instead of always making a new linguistic requests. And that the parts
   * only go up to 30.
   */
  public function testsAddPartsTranslationFlow(): void {
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
    \Drupal::state()->set('oe_translation_epoetry.dossiers', $dossiers);
    \Drupal::state()->set('oe_translation_epoetry_mock.last_request_reference', 2000);

    // Now create another node and start translating it.
    $second_node = $this->createBasicTestNode();
    $this->drupalGet($second_node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $select = $this->assertSession()->selectExists('Translator');
    $select->selectOption('epoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
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
    $this->assertSession()->pageTextContains('The translation request has been sent to DGT.');

    // Assert that the XML request we built is correct and it's using the
    // addNewPart.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(1, $requests);
    $xml = reset($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:addNewPartToDossier><dossier><requesterCode>DIGIT</requesterCode><number>2000</number><year>2023</year></dossier><requestDetails><title>A title prefix: A site ID - Basic translation node</title><internalReference>Translation request 2</internalReference><requestedDeadline>2032-10-10T12:00:00+00:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic translation node</fileName><comment>http://web:8080/build/en/basic-translation-node-0</comment><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMiIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJlbiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDI8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0yIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzJdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTWwxYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2032-10-10T12:00:00+00:00" trackChanges="false"><language>fr</language></product></products></requestDetails><applicationName>digit</applicationName><templateName>WEBTRA</templateName></ns1:addNewPartToDossier></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

    // Assert that the state dossier got updated correctly.
    $dossiers = \Drupal::state()->get('oe_translation_epoetry.dossiers');
    $this->assertEquals([
      2000 => [
        'part' => 1,
        'code' => 'DIGIT',
        'year' => date('Y'),
      ],
    ], $dossiers);

    // Now create yet another node and translate it. The parts should increment.
    $third_node = $this->createBasicTestNode();
    $this->drupalGet($third_node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $select = $this->assertSession()->selectExists('Translator');
    $select->selectOption('epoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
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
    $this->assertSession()->pageTextContains('The translation request has been sent to DGT.');
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(2, $requests);
    $xml = end($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:addNewPartToDossier><dossier><requesterCode>DIGIT</requesterCode><number>2000</number><year>2023</year></dossier><requestDetails><title>A title prefix: A site ID - Basic translation node</title><internalReference>Translation request 3</internalReference><requestedDeadline>2032-10-10T12:00:00+00:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic translation node</fileName><comment>http://web:8080/build/en/basic-translation-node-1</comment><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMyIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJlbiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDM8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0zIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzNdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTTExYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2032-10-10T12:00:00+00:00" trackChanges="false"><language>de</language></product></products></requestDetails><applicationName>digit</applicationName><templateName>WEBTRA</templateName></ns1:addNewPartToDossier></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);
    $dossiers = \Drupal::state()->get('oe_translation_epoetry.dossiers');
    $this->assertEquals([
      2000 => [
        'part' => 2,
        'code' => 'DIGIT',
        'year' => date('Y'),
      ],
    ], $dossiers);

    // Mimic that we have reached to part 30 and the dossier should be reset.
    $dossiers[2000]['part'] = 30;
    \Drupal::state()->set('oe_translation_epoetry.dossiers', $dossiers);

    // Create a new node and send a translation request.
    $fourth_node = $this->createBasicTestNode();
    $this->drupalGet($fourth_node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $select = $this->assertSession()->selectExists('Translator');
    $select->selectOption('epoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
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
    $this->assertSession()->pageTextContains('The translation request has been sent to DGT.');

    // Assert that the XML request we built is correct and it's using the
    // createNewLinguisticRequest.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(3, $requests);
    $xml = end($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:createLinguisticRequest><requestDetails><title>A title prefix: A site ID - Basic translation node</title><internalReference>Translation request 4</internalReference><requestedDeadline>2032-10-10T12:00:00+00:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic translation node</fileName><comment>http://web:8080/build/en/basic-translation-node-2</comment><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iNCIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJlbiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDQ8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS00Ij4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzRdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTkYxYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2032-10-10T12:00:00+00:00" trackChanges="false"><language>it</language></product></products></requestDetails><applicationName>digit</applicationName><templateName>WEBTRA</templateName></ns1:createLinguisticRequest></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

    $dossiers = \Drupal::state()->get('oe_translation_epoetry.dossiers');
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
    $this->assertSession()->fieldEnabled('Translator')->selectOption('epoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
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
    $this->assertSession()->pageTextContains('The translation request has been sent to DGT.');

    // Assert that the XML request we built is correct.
    $requests = \Drupal::state()->get('oe_translation_epoetry_mock.mock_requests');
    $this->assertCount(1, $requests);
    $xml = reset($requests);
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:createNewVersion><linguisticRequest><requestReference><dossier><requesterCode>DIGIT</requesterCode><number>2000</number><year>2023</year></dossier><productType>TRA</productType><part>0</part></requestReference><requestDetails><title>A title prefix: A site ID - Basic translation node - update</title><internalReference>Translation request 2</internalReference><requestedDeadline>2034-10-10T12:00:00+00:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><comment>Some message to the provider</comment><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient2" contactRole="RECIPIENT"/><contact userId="test_webmaster2" contactRole="WEBMASTER"/><contact userId="test_editor2" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic translation node - update</fileName><comment>http://web:8080/build/en/basic-translation-node-update</comment><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMiIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJlbiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDI8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0yIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzJdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTWwxYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlIC0gdXBkYXRlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2034-10-10T12:00:00+00:00" trackChanges="false"><language>de</language></product><product requestedDeadline="2034-10-10T12:00:00+00:00" trackChanges="false"><language>it</language></product></products></requestDetails></linguisticRequest><applicationName>digit</applicationName></ns1:createNewVersion></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

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
    $this->assertEquals(new LanguageWithStatus('de', 'Active'), $target_languages['de']);
    $this->assertEquals(new LanguageWithStatus('it', 'Active'), $target_languages['it']);
    $this->assertEquals('Active', $update_request->getRequestStatus());
    $this->assertEquals('SenttoDGT', $update_request->getEpoetryRequestStatus());
    $this->assertEquals('epoetry', $update_request->getTranslatorProvider()->id());
    $this->assertNull($update_request->getAcceptedDeadline());
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
      'Active',
      'ePoetry',
      'SenttoDGT',
      // The version got updated, not the number.
      'DIGIT/' . date('Y') . '/2000/1/0/TRA',
      'No',
      'No',
      '2034-Oct-10',
      'N/A',
      // The mock link to Accept.
      'Accept',
    ]);

    // We have the table of languages of this last request, all active but
    // none yet translated.
    $expected_languages = [];
    foreach (['de', 'it'] as $langcode) {
      $expected_languages[$langcode] = [
        'langcode' => $langcode,
        'status' => 'Active',
        'review' => FALSE,
      ];
    }
    $this->assertRemoteOngoingTranslationLanguages($expected_languages);
  }

  /**
   * Tests the access to create a new version for ongoing requests.
   *
   * In essence, tests that all criteria are met before a user can make a
   * createNewVersion request for an ongoing translation request. It doesn't
   * cover the cases in which a given request has already been translated by
   * DGT.
   */
  public function testAccessOngoingCreateVersion(): void {
    $cases = [
      'no new draft, sent to DGT request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_SENT,
        'create draft' => FALSE,
        'visible' => FALSE,
        'ongoing' => TRUE,
      ],
      'no new draft, accepted request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED,
        'create draft' => FALSE,
        'visible' => FALSE,
        'ongoing' => TRUE,
      ],
      'new draft, accepted request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED,
        'create draft' => TRUE,
        'visible' => TRUE,
        'ongoing' => TRUE,
      ],
      'new draft, executed request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_EXECUTED,
        'create draft' => TRUE,
        'visible' => FALSE,
        'ongoing' => TRUE,
      ],
      'new draft, suspended request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_SUSPENDED,
        'create draft' => TRUE,
        'visible' => FALSE,
        'ongoing' => FALSE,
      ],
      'new draft, cancelled request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_CANCELLED,
        'create draft' => TRUE,
        'visible' => FALSE,
        'ongoing' => FALSE,
      ],
    ];

    foreach ($cases as $case => $case_info) {
      // Create the node from scratch.
      $node = $this->createBasicTestNode();

      // Make an active request and set the case epoetry status.
      $request = $this->createNodeTranslationRequest($node);
      $request->setEpoetryRequestStatus($case_info['epoetry_status']);
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
        $request->setRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_ACTIVE);
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
    $this->assertSession()->pageTextContains('The translation request has been sent to DGT.');
    $this->assertSession()->pageTextContains(sprintf('The old request with the id %s has been marked as Finished.', $request->getRequestId(TRUE)));

    // We are back where we came from.
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations/remote');
    // We see still only one request, the new one, as the previous one was
    // marked as finished.
    $this->assertRequestStatusTable([
      'Active',
      'ePoetry',
      'SenttoDGT',
      // The version got updated, not the number.
      'DIGIT/' . date('Y') . '/2000/1/0/TRA',
      'No',
      'No',
      '2034-Oct-10',
      'N/A',
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
        'status' => 'Active',
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
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:createNewVersion><linguisticRequest><requestReference><dossier><requesterCode>DIGIT</requesterCode><number>2000</number><year>2023</year></dossier><productType>TRA</productType><part>0</part></requestReference><requestDetails><title>A title prefix: A site ID - Basic translation node - update</title><internalReference>Translation request 2</internalReference><requestedDeadline>2034-10-10T12:00:00+00:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><comment>Some message to the provider</comment><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient2" contactRole="RECIPIENT"/><contact userId="test_webmaster2" contactRole="WEBMASTER"/><contact userId="test_editor2" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic translation node - update</fileName><comment>http://web:8080/build/en/basic-translation-node-update</comment><content>PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPCFET0NUWVBFIGh0bWwgUFVCTElDICItLy9XM0MvL0RURCBYSFRNTCAxLjAgU3RyaWN0Ly9FTiIgImh0dHA6Ly93d3cudzMub3JnL1RSL3hodG1sMS9EVEQveGh0bWwxLXN0cmljdC5kdGQiPgo8aHRtbCB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94aHRtbCI+CiAgPGhlYWQ+CiAgICA8bWV0YSBodHRwLWVxdWl2PSJjb250ZW50LXR5cGUiIGNvbnRlbnQ9InRleHQvaHRtbDsgY2hhcnNldD11dGYtOCIgLz4KICAgIDxtZXRhIG5hbWU9InJlcXVlc3RJZCIgY29udGVudD0iMiIgLz4KICAgIDxtZXRhIG5hbWU9Imxhbmd1YWdlU291cmNlIiBjb250ZW50PSJlbiIgLz4KICAgIDx0aXRsZT5SZXF1ZXN0IElEIDI8L3RpdGxlPgogIDwvaGVhZD4KICA8Ym9keT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImFzc2V0IiBpZD0iaXRlbS0yIj4KICAgICAgICAgICAgICAgICAgPCEtLQogICAgICAgICAgbGFiZWw9IlRpdGxlIgogICAgICAgICAgY29udGV4dD0iWzJdW3RpdGxlXVswXVt2YWx1ZV0iCiAgICAgICAgICAtLT4KICAgICAgICAgIDxkaXYgY2xhc3M9ImF0b20iIGlkPSJiTWwxYmRHbDBiR1ZkV3pCZFczWmhiSFZsIj5CYXNpYyB0cmFuc2xhdGlvbiBub2RlIC0gdXBkYXRlPC9kaXY+CiAgICAgICAgICAgICAgPC9kaXY+CiAgICAgIDwvYm9keT4KPC9odG1sPgo=</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2034-10-10T12:00:00+00:00" trackChanges="false"><language>bg</language></product><product requestedDeadline="2034-10-10T12:00:00+00:00" trackChanges="false"><language>de</language></product><product requestedDeadline="2034-10-10T12:00:00+00:00" trackChanges="false"><language>it</language></product></products></requestDetails></linguisticRequest><applicationName>digit</applicationName></ns1:createNewVersion></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

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
    $this->assertEquals(new LanguageWithStatus('bg', 'Active'), $target_languages['bg']);
    $this->assertEquals(new LanguageWithStatus('de', 'Active'), $target_languages['de']);
    $this->assertEquals(new LanguageWithStatus('it', 'Active'), $target_languages['it']);
    $this->assertEquals('Active', $update_request->getRequestStatus());
    $this->assertEquals('SenttoDGT', $update_request->getEpoetryRequestStatus());
    $this->assertEquals('epoetry', $update_request->getTranslatorProvider()->id());
    $this->assertNull($update_request->getAcceptedDeadline());
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

    // Now test that we can make update requests to ongoing requests even if
    // we have already a translated request (not yet synced).
    // Start by translating directly this new request so we have a translated
    // request.
    EpoetryTranslationMockHelper::translateRequest($update_request, 'bg');
    EpoetryTranslationMockHelper::translateRequest($update_request, 'de');
    EpoetryTranslationMockHelper::translateRequest($update_request, 'it');
    $update_request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_EXECUTED);
    $update_request->save();

    $this->getSession()->reload();
    $this->assertRequestStatusTable([
      'Translated',
      'ePoetry',
      'Executed',
      'DIGIT/' . date('Y') . '/2000/1/0/TRA',
      'No',
      'No',
      '2034-Oct-10',
      'N/A',
    ]);

    // Next, create a new request, accept it from DGT and then make an update
    // to this request.
    $this->assertSession()->selectExists('Translator')->selectOption('epoetry');
    $this->assertSession()->assertWaitOnAjaxRequest();
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
    $this->assertSession()->pageTextContains('The translation request has been sent to DGT.');
    $expected_ongoing_one = [
      'translator' => 'ePoetry',
      'status' => 'Translated',
      'title' => 'Basic translation node - update',
      'title_url' => $node->toUrl('revision')->toString(),
      'revision' => $node->getRevisionId(),
      'is_default' => 'Yes',
    ];
    $expected_ongoing_two = $expected_ongoing_one;
    // They are both for the same revision, except the second one is Active.
    $expected_ongoing_two['status'] = 'Active';
    $this->assertOngoingTranslations([
      $expected_ongoing_one,
      $expected_ongoing_two,
    ]);

    // Accept this request, make a new draft and make an update request.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE, [TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED]);
    $this->assertCount(1, $requests);
    $request = reset($requests);
    $this->getSession()->getPage()->find('xpath', "//tr[td//text()[contains(., 'Active')]]")->clickLink('View');
    $this->assertSession()->linkNotExistsExact('Update');
    $this->getSession()->getPage()->clickLink('Accept');
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
    $this->assertSession()->pageTextContains('The translation request has been sent to DGT.');
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
      ],
      'accepted request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED,
        'visible' => TRUE,
        'ongoing' => TRUE,
      ],
      'executed request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_EXECUTED,
        'visible' => FALSE,
        'ongoing' => TRUE,
      ],
      'suspended request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_SUSPENDED,
        'visible' => FALSE,
        'ongoing' => FALSE,
      ],
      'cancelled request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_CANCELLED,
        'visible' => FALSE,
        'ongoing' => FALSE,
      ],
    ];

    foreach ($cases as $case => $case_info) {
      // Create the node from scratch.
      $node = $this->createBasicTestNode();

      // Make an active request and set the case epoetry status.
      $request = $this->createNodeTranslationRequest($node);
      $request->setEpoetryRequestStatus($case_info['epoetry_status']);
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
        foreach (['Translated', 'Finished', 'Failed'] as $status) {
          $request->setRequestStatus($status);
          $request->save();
        }

        $this->getSession()->reload();
        $this->assertSession()->pageTextNotContains('Add new languages');
        $this->assertSession()->linkNotExistsExact('Add new languages');

        // Set back the active status for the next iteration.
        $request->setRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_ACTIVE);
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
      'Active',
      'ePoetry',
      'Accepted',
      'DIGIT/' . date('Y') . '/2000/0/0/TRA',
      'No',
      'No',
      '2035-Oct-10',
      'N/A',
      'Cancel',
    ]);

    // We have a single language.
    $expected_languages = [];
    foreach (['fr'] as $langcode) {
      $expected_languages[$langcode] = [
        'langcode' => $langcode,
        'status' => 'Active',
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

    // Assert that FR is disabled and checked because the ongoing request
    // is for FR.
    $this->assertSession()->fieldDisabled('French');
    $this->assertSession()->checkboxChecked('French');
    // Add another language and submit the request.
    $this->getSession()->getPage()->checkField('German');
    $this->getSession()->getPage()->pressButton('Save and send');

    $this->assertSession()->pageTextContains('The translation request has been sent to DGT.');

    // We are back where we came from.
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations/remote');
    // Our request should stay unchanged, apart from the new language addition.
    $this->assertRequestStatusTable([
      'Active',
      'ePoetry',
      'Accepted',
      'DIGIT/' . date('Y') . '/2000/0/0/TRA',
      'No',
      'No',
      '2035-Oct-10',
      'N/A',
      'Cancel',
    ]);

    // We now have 2 translations.
    $expected_languages = [];
    foreach (['fr', 'de'] as $langcode) {
      $expected_languages[$langcode] = [
        'langcode' => $langcode,
        'status' => 'Active',
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
    $this->assertEquals(new LanguageWithStatus('fr', 'Active'), $target_languages['fr']);
    $this->assertEquals(new LanguageWithStatus('de', 'Active'), $target_languages['de']);
    $this->assertCount(2, $target_languages);
    $this->assertEquals('Active', $request->getRequestStatus());
    $this->assertEquals('Accepted', $request->getEpoetryRequestStatus());
    $this->assertEquals('epoetry', $request->getTranslatorProvider()->id());
    $this->assertNull($request->getAcceptedDeadline());
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
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:modifyLinguisticRequest><modifyLinguisticRequest><requestReference><dossier><requesterCode>DIGIT</requesterCode><number>2000</number><year>2023</year></dossier><productType>TRA</productType><part>0</part></requestReference><requestDetails><contacts xsi:type="ns1:contacts"><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><products xsi:type="ns1:products"><product requestedDeadline="2035-10-10T12:00:00+00:00" trackChanges="false"><language>de</language></product></products></requestDetails></modifyLinguisticRequest><applicationName>digit</applicationName></ns1:modifyLinguisticRequest></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);
  }

  /**
   * Creates a translation request for a given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $status
   *   The request status.
   * @param array $languages
   *   The language data (status + langcode.)
   *
   * @return \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface
   *   The resulting request.
   */
  protected function createNodeTranslationRequest(NodeInterface $node, string $status = TranslationRequestEpoetryInterface::STATUS_REQUEST_ACTIVE, array $languages = []): TranslationRequestEpoetryInterface {
    if (!$languages) {
      $languages[] = [
        'status' => TranslationRequestEpoetryInterface::STATUS_LANGUAGE_ACTIVE,
        'langcode' => 'fr',
      ];
    }

    /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
    $request = TranslationRequestEpoetry::create([
      'bundle' => 'epoetry',
      'source_language_code' => $node->language()->getId(),
      'target_languages' => $languages,
      'translator_provider' => 'epoetry',
    ]);

    $date = new DrupalDateTime('2035-Oct-10');
    $request->setContentEntity($node);
    $data = \Drupal::service('oe_translation.translation_source_manager')->extractData($node->getUntranslated());
    $request->setData($data);
    $request->setRequestStatus($status);
    $request->setAutoAccept(FALSE);
    $request->setAutoSync(FALSE);
    $request->setDeadline($date);
    $request->setContacts([
      'Recipient' => 'test_recipient',
      'Webmaster' => 'test_webmaster',
      'Editor' => 'test_editor',
    ]);
    $request->setRequestId(
      [
        'code' => 'DIGIT',
        'year' => date('Y'),
        'number' => 2000,
        'part' => 0,
        'version' => 0,
        'service' => 'TRA',
      ]
    );

    // Set expected ePoetry statuses based on the request status.
    if ($status == TranslationRequestEpoetryInterface::STATUS_REQUEST_ACTIVE) {
      $request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_SENT);
    }
    if (in_array($status, [
      TranslationRequestEpoetryInterface::STATUS_REQUEST_TRANSLATED,
      TranslationRequestEpoetryInterface::STATUS_REQUEST_FINISHED,
    ])) {
      $request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_EXECUTED);
    }

    return $request;
  }

}
