<?php
/**
 * @file
 */

namespace Shopify;


/**
 * Class ShopifyMissingScopesException
 *
 * If a request is made to shopify and api permissions has not been granted, a missing scopes exception will be thrown.
 *
 * @package Shopify
 */
class ShopifyMissingScopesException extends ClientException {

  protected $missing_scopes = [];

  /**
   * ShopifyMissingScopesException constructor.
   *
   * @param string $message
   * @param int $code
   * @param \Exception|null $previous
   * @param \Shopify\Client|null $client
   * @param array $missing_scopes
   */
  public function __construct($message = '', $code = 0, \Exception $previous = NULL, Client $client = NULL, array $missing_scopes = []) {
    // add our missing scope
    $this->missing_scopes = $missing_scopes;

    parent::__construct($message, $code, $previous, $client);
  }

  /**
   * Return the missing scopes.
   *
   * @return array
   */
  public function getMissingScopes() {
    return $this->missing_scopes;
  }
}
