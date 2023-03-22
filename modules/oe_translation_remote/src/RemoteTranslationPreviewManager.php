<?php

declare(strict_types=1);

namespace Drupal\oe_translation_remote;

use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation\TranslationPreviewManager;
use Drupal\oe_translation\TranslationSourceManagerInterface;
use Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager;

/**
 * Translation preview manager for remote translations.
 */
class RemoteTranslationPreviewManager extends TranslationPreviewManager {

  /**
   * The remote translation provider manager.
   *
   * @var \Drupal\oe_translation_remote\Plugin\RemoteTranslationProviderManager
   */
  protected $providerManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(TranslationSourceManagerInterface $translationSourceManager, RemoteTranslationProviderManager $providerManager) {
    parent::__construct($translationSourceManager);
    $this->providerManager = $providerManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function extractPreviewData(TranslationRequestInterface $oe_translation_request, string $language): array {
    $bundles = $this->providerManager->getRemoteTranslationBundles();
    if (!in_array($oe_translation_request->bundle(), $bundles)) {
      return parent::extractPreviewData($oe_translation_request, $language);
    }

    // Retrieve the entity being translated and its data.
    $entity = $oe_translation_request->getContentEntity();
    if (!$entity) {
      throw new \Exception('There is no entity currently being translated.');
    }
    $data = $oe_translation_request->getTranslatedData();

    return $data[$language] ?? [];
  }

}
