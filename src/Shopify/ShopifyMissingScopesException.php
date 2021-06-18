<?php
/**
 * @file
 */

namespace Shopify;


/**
 * Class ShopifyApiScopesException
 *
 * @package Drupal\ta_app\Exception
 */
class ShopifyMissingScopesException extends ClientException {

  protected $missing_scopes = [];

  // Redefine the exception so message isn't optional
  public function __construct($message = '', $code = 0, \Exception $previous = NULL, Client $client = NULL, array $missing_scopes = []) {
    // add our missing scope
    $this->missing_scopes = $missing_scopes;

    parent::__construct($message, $code, $previous, $client);
  }

  /**
   * Return the missing scopes
   * @return array
   */
  public function getMissingScopes() {
    return $this->missing_scopes;
  }
}
