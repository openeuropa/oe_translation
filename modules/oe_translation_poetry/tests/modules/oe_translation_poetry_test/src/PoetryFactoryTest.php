<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry_test;

use Drupal\oe_translation_poetry\PoetryFactory;
use Drupal\oe_translation_poetry\PoetryInterface;
use Drupal\tmgmt\TranslatorInterface;

/**
 * Overriding the Poetry factory with a test version.
 */
class PoetryFactoryTest extends PoetryFactory {

  /**
   * {@inheritdoc}
   */
  public function get(string $translator): PoetryInterface {
    $entity = $this->entityTypeManager->getStorage('tmgmt_translator')->load($translator);
    $settings = [];
    if ($entity instanceof TranslatorInterface) {
      $settings = $entity->get('settings');
    }

    return new PoetryTest($settings, $this->configFactory, $this->loggerFactory->get('poetry'), $this->state, $this->entityTypeManager, $this->database, $this->requestStack);
  }

}
