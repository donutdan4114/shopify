<?php

namespace Shopify;

class ClientException extends \Exception {

  private $client;

  public function __construct($message = "", $code = 0, \Exception $previous = NULL, Client $client) {
    $this->client = $client;
    parent::__construct($message, $code, $previous);
  }

  public function getErrors() {
    return $this->client->getErrors();
  }

  public function getLastResponse() {
    return $this->client->getLastResponse();
  }

}
