<?php

namespace Shopify;

/**
 * Class PrivateApp
 *
 * @package Shopify
 *
 * Used for Private Apps where access_token isn't required.
 */
class PrivateApp extends Client {

  /**
   * Private app credentials.
   * See: [your-domain].myshopify.com/admin/apps/private
   *
   * @param string $shop_domain
   *   Shopify domain.
   * @param string $api_key
   *   Shopify API Key.
   * @param string $password
   *   Shopify API Password.
   * @param string $shared_secret
   *   Shopify API Shared Secret.
   * @param array $opts
   *   Default options to set.
   */
  public function __construct($shop_domain, $api_key, $password, $shared_secret, array $opts = []) {
    $this->shop_domain = $shop_domain;
    $this->password = $password;
    $this->shared_secret = $shared_secret;
    $this->api_key = $api_key;
    $this->client_type = 'private';

    if (isset($opts['version'])) {
      $this->version = $opts['version'];
      unset($opts['version']);
    }

    $opts['base_uri'] = $this->getApiUrl();
    $this->client = $this->getNewHttpClient($opts);
  }
}
