<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Controller;

use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt_content\Controller\ContentTranslationPreviewController;

/**
 * Extends the original TMGMT preview controller.
 */
class ContentPreviewController extends ContentTranslationPreviewController {

  /**
   * {@inheritdoc}
   */
  public function title(JobItemInterface $tmgmt_job_item) {
    $target_language = $tmgmt_job_item->getJob()->getTargetLanguage();
    $data = $tmgmt_job_item->getData();
    $entity = $this->entityTypeManager
      ->getStorage($tmgmt_job_item->getItemType())
      ->load($tmgmt_job_item->getItemId());

    // Populate preview with target translation data.
    $preview = $this->makePreview($entity, $data, $target_language->getId());

    return $preview->label();
  }

}
