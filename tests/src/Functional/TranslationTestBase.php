<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\oe_translation\Traits\TranslationsTestTrait;

/**
 * Base class for functional tests.
 */
class TranslationTestBase extends BrowserTestBase {

  use TranslationsTestTrait;

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
    'oe_translation_local',
    'oe_translation_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->entityTypeManager->getStorage('node_type')->create([
      'name' => 'Page',
      'type' => 'page',
    ])->save();

    $this->container->get('content_translation.manager')->setEnabled('node', 'page', TRUE);
    $this->container->get('router.builder')->rebuild();

    $this->drupalPlaceBlock('page_title_block', ['region' => 'content']);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content']);

    $user = $this->setUpTranslatorUser();
    $this->drupalLogin($user);
  }

}
