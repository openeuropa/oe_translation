<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Base class for functional javsacript tests.
 */
class TranslationTestBase extends WebDriverTestBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'tmgmt',
    'tmgmt_content',
    'oe_multilingual',
    'node',
    'toolbar',
    'content_translation',
    'user',
    'field',
    'text',
    'options',
    'oe_translation',
    'oe_translation_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->entityTypeManager->getStorage('node_type')->create([
      'name' => 'Page',
      'type' => 'page',
    ])->save();

    $this->container->get('content_translation.manager')->setEnabled('node', 'page', TRUE);
    $this->container->get('router.builder')->rebuild();

    /** @var \Drupal\user\RoleInterface $role */
    $role = $this->entityTypeManager->getStorage('user_role')->load('oe_translator');
    $permissions = $role->getPermissions();
    $permissions[] = 'administer menu';
    $user = $this->drupalCreateUser($permissions);

    $this->drupalLogin($user);
  }

}
