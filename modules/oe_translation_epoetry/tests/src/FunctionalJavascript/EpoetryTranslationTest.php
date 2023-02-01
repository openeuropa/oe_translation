<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_epoetry\FunctionalJavascript;

use Drupal\oe_translation\LanguageWithStatus;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\oe_translation_remote\Entity\RemoteTranslatorProvider;
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
    $this->assertEquals('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://eu.europa.ec.dgt.epoetry" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Header xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws"><ecas:ProxyTicket xmlns:ecas="https://ecas.ec.europa.eu/cas/schemas/ws">ticket</ecas:ProxyTicket></soap:Header><SOAP-ENV:Body><ns1:createLinguisticRequest><requestDetails><title>A title prefix: A site ID - Basic translation node</title><internalReference>Translation request 1</internalReference><requestedDeadline>2032-10-10T12:00:00+00:00</requestedDeadline><destination>PUBLIC</destination><procedure>NEANT</procedure><slaAnnex>NO</slaAnnex><comment>Message to the provider</comment><accessibleTo>CONTACTS</accessibleTo><contacts><contact userId="test_recipient" contactRole="RECIPIENT"/><contact userId="test_webmaster" contactRole="WEBMASTER"/><contact userId="test_editor" contactRole="EDITOR"/></contacts><originalDocument><fileName>Basic translation node</fileName><comment>http://web:8080/build/en/basic-translation-node</comment><content>UEQ5NGJXd2dkbVZ5YzJsdmJqMGlNUzR3SWlCbGJtTnZaR2x1WnowaVZWUkdMVGdpUHo0S1BDRkVUME5VV1ZCRklHaDBiV3dnVUZWQ1RFbERJQ0l0THk5WE0wTXZMMFJVUkNCWVNGUk5UQ0F4TGpBZ1UzUnlhV04wTHk5RlRpSWdJbWgwZEhBNkx5OTNkM2N1ZHpNdWIzSm5MMVJTTDNob2RHMXNNUzlFVkVRdmVHaDBiV3d4TFhOMGNtbGpkQzVrZEdRaVBnbzhhSFJ0YkNCNGJXeHVjejBpYUhSMGNEb3ZMM2QzZHk1M015NXZjbWN2TVRrNU9TOTRhSFJ0YkNJK0NpQWdQR2hsWVdRK0NpQWdJQ0E4YldWMFlTQm9kSFJ3TFdWeGRXbDJQU0pqYjI1MFpXNTBMWFI1Y0dVaUlHTnZiblJsYm5ROUluUmxlSFF2YUhSdGJEc2dZMmhoY25ObGREMTFkR1l0T0NJZ0x6NEtJQ0FnSUR4dFpYUmhJRzVoYldVOUluSmxjWFZsYzNSSlpDSWdZMjl1ZEdWdWREMGlNU0lnTHo0S0lDQWdJRHh0WlhSaElHNWhiV1U5SW14aGJtZDFZV2RsVTI5MWNtTmxJaUJqYjI1MFpXNTBQU0psYmlJZ0x6NEtJQ0FnSUR4MGFYUnNaVDVTWlhGMVpYTjBJRWxFSURFOEwzUnBkR3hsUGdvZ0lEd3ZhR1ZoWkQ0S0lDQThZbTlrZVQ0S0lDQWdJQ0FnSUNBZ0lEeGthWFlnWTJ4aGMzTTlJbUZ6YzJWMElpQnBaRDBpYVhSbGJTMHhJajRLSUNBZ0lDQWdJQ0FnSUNBZ0lDQWdJQ0FnUENFdExRb2dJQ0FnSUNBZ0lDQWdiR0ZpWld3OUlsUnBkR3hsSWdvZ0lDQWdJQ0FnSUNBZ1kyOXVkR1Y0ZEQwaVd6RmRXM1JwZEd4bFhWc3dYVnQyWVd4MVpWMGlDaUFnSUNBZ0lDQWdJQ0F0TFQ0S0lDQWdJQ0FnSUNBZ0lEeGthWFlnWTJ4aGMzTTlJbUYwYjIwaUlHbGtQU0ppVFZZeFltUkhiREJpUjFaa1YzcENaRmN6V21oaVNGWnNJajVDWVhOcFl5QjBjbUZ1YzJ4aGRHbHZiaUJ1YjJSbFBDOWthWFkrQ2lBZ0lDQWdJQ0FnSUNBZ0lDQWdQQzlrYVhZK0NpQWdJQ0FnSUR3dlltOWtlVDRLUEM5b2RHMXNQZ289</content><linguisticSections><linguisticSection xsi:type="ns1:linguisticSectionOut"><language>EN</language></linguisticSection></linguisticSections><trackChanges>false</trackChanges></originalDocument><products><product requestedDeadline="2032-10-10T12:00:00+00:00" trackChanges="false"><language>bg</language></product><product requestedDeadline="2032-10-10T12:00:00+00:00" trackChanges="false"><language>fr</language></product></products></requestDetails><applicationName>digit</applicationName><templateName>WEBTRA</templateName></ns1:createLinguisticRequest></SOAP-ENV:Body></SOAP-ENV:Envelope>', $xml);

    // Assert that we got back a correct response and our request was correctly
    // updated.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    $this->assertCount(1, $requests);
    $request = reset($requests);
    $this->assertInstanceOf(TranslationRequestEpoetryInterface::class, $request);
    $this->assertEquals('en', $request->getSourceLanguageCode());
    $target_languages = $request->getTargetLanguages();
    $this->assertEquals(new LanguageWithStatus('bg', 'active'), $target_languages['bg']);
    $this->assertEquals(new LanguageWithStatus('fr', 'active'), $target_languages['fr']);
    $this->assertEquals('active', $request->getRequestStatus());
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
      'active',
      'ePoetry',
      'DIGIT/' . date('Y') . '/1001/0/0/TRA',
      'No',
      'No',
      '2032-Oct-10',
      'N/A',
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
  }

}
