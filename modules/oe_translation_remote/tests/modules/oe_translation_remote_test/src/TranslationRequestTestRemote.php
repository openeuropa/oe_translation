<?php

declare(strict_types=1);

namespace Drupal\oe_translation_remote_test;

use Drupal\oe_translation\Entity\TranslationRequest;
use Drupal\oe_translation_remote\RemoteTranslationRequestEntityTrait;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;

/**
 * Bundle class for the test remote TranslationRequest entity.
 */
class TranslationRequestTestRemote extends TranslationRequest implements TranslationRequestRemoteInterface {

  use RemoteTranslationRequestEntityTrait;

}
