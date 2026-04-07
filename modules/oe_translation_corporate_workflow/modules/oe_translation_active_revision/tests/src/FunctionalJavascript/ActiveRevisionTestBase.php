<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_active_revision\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\oe_editorial\Traits\BatchTrait;
use Drupal\Tests\oe_editorial_corporate_workflow\Traits\CorporateWorkflowTrait;
use Drupal\Tests\oe_translation\Traits\EntityVersionTrait;
use Drupal\Tests\oe_translation\Traits\TranslationsTestTrait;
use Drupal\user\Entity\Role;

/**
 * Base class for the active revision tests.
 *
 * @group batch3
 */
abstract class ActiveRevisionTestBase extends WebDriverTestBase {

  use BatchTrait;
  use EntityVersionTrait;
  use TranslationsTestTrait;
  use CorporateWorkflowTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The version field name.
   *
   * @var string
   */
  protected string $versionFieldName;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'toolbar',
    'content_translation',
    'user',
    'field',
    'text',
    'options',
    'block',
    'oe_editorial_workflow_demo',
    'oe_translation',
    'oe_translation_corporate_workflow',
    'oe_translation_local',
    'oe_translation_active_revision',
    'oe_translation_active_revision_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = \Drupal::entityTypeManager();

    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);
    \Drupal::service('oe_editorial_corporate_workflow.workflow_installer')->installWorkflow('page');
    $this->versionFieldName = $this->installEntityVersionField('node', 'page');

    \Drupal::service('router.builder')->rebuild();

    $user = $this->setUpTranslatorUser();
    // Grant the editorial roles.
    foreach (['oe_author', 'oe_reviewer', 'oe_validator'] as $role) {
      $user->addRole($role);
      $user->save();
    }

    $role = Role::load('oe_translator');
    $role->grantPermission('delete any page content');
    $role->grantPermission('delete content translations');
    $role->grantPermission('delete all revisions');
    $role->save();

    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    $this->rebuildContainer();
    $this->drupalLogin($user);
  }

  /**
   * Asserts the operations links.
   *
   * @param array $expected
   *   The expected links titles.
   * @param \Behat\Mink\Element\NodeElement[] $actual
   *   The actual links titles.
   */
  protected function assertOperationLinks(array $expected, array $actual): void {
    $operations = [];
    foreach ($actual as $item) {
      $operations[] = $item->getHtml();
    }

    $expected = array_keys(array_filter($expected));
    $this->assertEquals($expected, $operations);
  }

  /**
   * Returns the select options of a given select field.
   *
   * @param string $select
   *   The select field title.
   *
   * @return array
   *   The options, keyed by value.
   */
  protected function getSelectOptions(string $select): array {
    $select = $this->assertSession()->selectExists($select);
    $options = [];
    foreach ($select->findAll('xpath', '//option') as $element) {
      $options[$element->getValue()] = trim($element->getText());
    }

    return $options;
  }

}
