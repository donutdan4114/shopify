<?php

namespace Shopify;

/**
 * Class ClientException
 *
 * @package Shopify
 */
class ClientException extends \Exception {

  /**
   * @var \Shopify\Client
   */
  private $client;

  /**
   * ClientException constructor.
   *
   * @param string $message
   * @param int $code
   * @param \Exception|NULL $previous
   * @param \Shopify\Client $client
   */
  public function __construct($message = "", $code = 0, \Exception $previous = NULL, Client $client) {
    $this->client = $client;
    parent::__construct($message, $code, $previous);
  }

  /**
   * @return array
   */
  public function getErrors() {
    return $this->client->getErrors();
  }

  /**
   * @return \Psr\Http\Message\ResponseInterface
   */
  public function getLastResponse() {
    return $this->client->getLastResponse();
  }
}
