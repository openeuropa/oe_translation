<?php

namespace Drupal\oe_translation_active_revision;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining an activerevision entity type.
 */
interface ActiveRevisionInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
