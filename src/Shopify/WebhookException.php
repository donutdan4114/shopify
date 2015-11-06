<?php

namespace Shopify;

class WebhookException extends \Exception {

  private $data;
  private $hmac_header;

  public function __construct($message = "", $code = 0, \Exception $previous = NULL, $data = '', $hmac_header = '') {
    parent::__construct($message, $code, $previous);
  }

  public function getData() {
    return $this->data;
  }

  public function getHmacHeader() {
    return $this->hmac_header;
  }

}
