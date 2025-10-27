<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation\Traits;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;

/**
 * Generic trait for testing the translation system.
 */
trait TranslationsTestTrait {

  /**
   * Creates a test node with all the field values.
   *
   * @return \Drupal\node\NodeInterface
   *   The created node.
   */
  protected function createFullTestNode(): NodeInterface {
    // Create a node to be referenced.
    $referenced_node = Node::create([
      'type' => 'oe_demo_translatable_page',
      'title' => 'Referenced node',
    ]);
    $referenced_node->save();

    // Create paragraphs to reference and translate.
    $grandchild_paragraph_one = Paragraph::create([
      'type' => 'demo_inner_paragraph_type',
      'ott_inner_paragraph_field' => 'grandchild field value 1',
    ]);
    $grandchild_paragraph_one->save();

    $grandchild_paragraph_two = Paragraph::create([
      'type' => 'demo_inner_paragraph_type',
      'ott_inner_paragraph_field' => 'grandchild field value 2',
    ]);
    $grandchild_paragraph_two->save();

    $child_paragraph_one = Paragraph::create([
      'type' => 'demo_inner_paragraph_type',
      'ott_inner_paragraph_field' => 'child field value 1',
      'ott_inner_paragraphs' => [
        [
          'target_id' => $grandchild_paragraph_one->id(),
          'target_revision_id' => $grandchild_paragraph_one->getRevisionId(),
        ],
        [
          'target_id' => $grandchild_paragraph_two->id(),
          'target_revision_id' => $grandchild_paragraph_two->getRevisionId(),
        ],
      ],
    ]);
    $child_paragraph_one->save();

    $child_paragraph_two = Paragraph::create([
      'type' => 'demo_inner_paragraph_type',
      'ott_inner_paragraph_field' => 'child field value 2',
    ]);
    $child_paragraph_two->save();

    $top_paragraph_one = Paragraph::create([
      'type' => 'demo_paragraph_type',
      'ott_top_level_paragraph_field' => 'top field value 1',
      'ott_inner_paragraphs' => [
        [
          'target_id' => $child_paragraph_one->id(),
          'target_revision_id' => $child_paragraph_one->getRevisionId(),
        ],
        [
          'target_id' => $child_paragraph_two->id(),
          'target_revision_id' => $child_paragraph_two->getRevisionId(),
        ],
      ],
    ]);
    $top_paragraph_one->save();

    $top_paragraph_two = Paragraph::create([
      'type' => 'demo_paragraph_type',
      'ott_top_level_paragraph_field' => 'top field value 2',
    ]);
    $top_paragraph_two->save();

    // Create the node with references to the paragraphs and the referenced
    // node.
    $node = Node::create([
      'type' => 'oe_demo_translatable_page',
      'title' => 'Full translation node',
      'ott_top_level_paragraphs' => [
        [
          'target_id' => $top_paragraph_one->id(),
          'target_revision_id' => $top_paragraph_one->getRevisionId(),
        ],
        [
          'target_id' => $top_paragraph_two->id(),
          'target_revision_id' => $top_paragraph_two->getRevisionId(),
        ],
      ],
      'ott_address' => [
        'country_code' => 'BE',
        'given_name' => 'The first name',
        'family_name' => 'The last name',
        'locality' => 'Brussels',
        'postal_code' => '1000',
        'address_line1' => 'The street name',
      ],
      'ott_content_reference' => $referenced_node->id(),
    ]);

    $node->save();

    return $node;
  }

  /**
   * Creates a test node with minimal field values.
   *
   * @param string $bundle
   *   The node bundle.
   * @param string $title
   *   The title.
   *
   * @return \Drupal\node\NodeInterface
   *   The created node.
   */
  protected function createBasicTestNode(string $bundle = 'oe_demo_translatable_page', string $title = 'Basic translation node'): NodeInterface {
    $node = Node::create([
      'type' => $bundle,
      'title' => $title,
    ]);

    $node->save();
    return $node;
  }

  /**
   * Loads a config array from storage and imports it.
   *
   * @param string $name
   *   The config name.
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The configuration storage where the file is located.
   * @param bool $create_if_missing
   *   If the configuration entity should be created if not found. Defaults to
   *   TRUE.
   */
  protected function importConfigFromFile(string $name, StorageInterface $storage, bool $create_if_missing = TRUE): void {
    $config_manager = \Drupal::service('config.manager');
    $entity_type_manager = \Drupal::entityTypeManager();

    $config = $storage->read($name);
    if (!$config) {
      throw new \LogicException(sprintf('The configuration value named %s was not found in the storage.', $name));
    }

    $entity_type = $config_manager->getEntityTypeIdByName($name);
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $entity_storage */
    $entity_storage = $entity_type_manager->getStorage($entity_type);
    $id_key = $entity_storage->getEntityType()->getKey('id');
    $entity = $entity_storage->load($config[$id_key]);
    if (!$entity instanceof ConfigEntityInterface) {
      if (!$create_if_missing) {
        throw new \LogicException(sprintf('The configuration entity "%s" was not found.', $config[$id_key]));
      }

      $entity = $entity_storage->createFromStorageRecord($config);
      $entity->save();

      return;
    }

    $entity = $entity_storage->updateFromStorageRecord($entity, $config);
    $entity->save();
  }

  /**
   * Creates a local translation request for a given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $langcode
   *   The target language.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface
   *   The translation request.
   */
  protected function createLocalTranslationRequest(ContentEntityInterface $entity, string $langcode): TranslationRequestInterface {
    /** @var \Drupal\oe_translation_local\TranslationRequestLocal $request */
    $request = $this->entityTypeManager->getStorage('oe_translation_request')
      ->create([
        'bundle' => 'local',
        'source_language_code' => $entity->getUntranslated()->language()->getId(),
      ]);
    $request->setTargetLanguage($langcode);
    $request->setContentEntity($entity);
    $data = \Drupal::service('oe_translation.translation_source_manager')->extractData($entity->getUntranslated());
    $request->setData($data);
    $request->save();

    return $request;
  }

  /**
   * Sets up a user with the translator role that can also create content.
   *
   * @return \Drupal\user\UserInterface
   *   The user.
   */
  protected function setUpTranslatorUser(): UserInterface {
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('oe_translator');
    $role->grantPermission('administer menu');
    $role->grantPermission('edit any page content');
    $role->grantPermission('translate editable entities');
    $role->grantPermission('create content translations');
    $role->grantPermission('update content translations');
    $role->save();
    $user = $this->drupalCreateUser();
    $user->addRole($role->id());
    $user->save();
    return $user;
  }

  /**
   * Asserts the existing translations table.
   *
   * @param array $languages
   *   The expected languages.
   */
  protected function assertDashboardExistingTranslations(array $languages): void {
    $table = $this->getSession()->getPage()->find('css', 'table.existing-translations-table');
    $rows = array_filter($table->findAll('css', 'tbody tr'), function (NodeElement $row) {
      // Filter out the rows that don't have a translation.
      return $row->find('xpath', '//td[2]')->getText() !== 'No translation';
    });
    $this->assertCount(count($languages), $rows);
    foreach ($rows as $row) {
      $cols = $row->findAll('css', 'td');
      $hreflang = $row->getAttribute('hreflang');
      $expected_info = $languages[$hreflang];
      $language = ConfigurableLanguage::load($hreflang);
      $this->assertEquals($language->getName(), $cols[0]->getText());
      $this->assertEquals($expected_info['title'], $cols[1]->getText());
      if ($row->getAttribute('hreflang') === 'en') {
        $this->assertEmpty($cols[2]->getText());
      }
    }
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
  protected function assertRequestStatus(string $expected_status, NodeElement $column): void {
    $text = $column->getText();
    if (str_contains($text, 'ⓘ')) {
      $text = trim(str_replace('ⓘ', '', $text));
    }

    $this->assertEquals($expected_status, $text);
    switch ($expected_status) {
      case TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED:
        $this->assertEquals('The request has been made to the external service.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED:
        $this->assertEquals('The translations for all languages have arrived from the remote provider and at least one is still not synchronised with the content.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED:
        $this->assertEquals('All translations have been synchronised with the content.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED_FINISHED:
        $this->assertEquals('The request has failed but it has been manually marked as finished so a new request can be made to the remote provider.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;

      case TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED:
        $this->assertEquals('The request has failed. You should mark it as "Failed &amp; Finished" in order to retry a new request.', $column->find('css', '.oe-translation-tooltip--text')->getHtml());
        return;
    }

    throw new \Exception(sprintf('The %s status tooltip is not covered by any assertion', $expected_status));
  }

}
