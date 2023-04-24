<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_active_revision;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining an active revision entity type.
 */
interface ActiveRevisionInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
