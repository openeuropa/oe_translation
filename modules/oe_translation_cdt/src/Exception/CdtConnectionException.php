<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Exception;

/**
 * Defines an Exception class for CDT API connection.
 *
 * This is identical to the base Exception class, we just give it a more
 * specific name so that call sites that want to tell the difference can
 * specifically catch these exceptions and treat them differently.
 */
class CdtConnectionException extends \Exception {

}
