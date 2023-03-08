<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use OpenEuropa\EPoetry\Authentication\ClientCertificate\ClientCertificateAuthentication as OriginalClientCertificateAuthentication;

/**
 * Handles authentication via EU Login using a certificate.
 */
class ClientCertificateAuthentication extends OriginalClientCertificateAuthentication {

  /**
   * {@inheritdoc}
   */
  public function __construct(LoggerChannelFactoryInterface $loggerChannelFactory) {
    // Default to empty string in case there are no env variables to prevent it
    // from crashing.
    $service_url = Settings::get('epoetry.auth.cert_service_url') ? Settings::get('epoetry.auth.cert_service_url') : '';
    $cert_file_path = Settings::get('epoetry.auth.cert_path', '') ? Settings::get('epoetry.auth.cert_path', '') : '';
    $cert_password = Settings::get('epoetry.auth.cert_password', '') ? Settings::get('epoetry.auth.cert_password', '') : '';
    $eu_login_base_path = Settings::get('epoetry.auth.eulogin_base_path', '') ? Settings::get('epoetry.auth.eulogin_base_path', '') : '';
    parent::__construct($service_url, $cert_file_path, $cert_password, $eu_login_base_path, $loggerChannelFactory->get('oe_translation_epoetry'));
  }

}
