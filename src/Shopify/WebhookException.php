<?php

namespace Shopify;

/**
 * Class WebhookException
 *
 * @package Shopify
 */
class WebhookException extends \Exception {

  private $data;

  private $hmac_header;

  /**
   * WebhookException constructor.
   *
   * @param string $message
   * @param int $code
   * @param \Exception|NULL $previous
   * @param string $data
   * @param string $hmac_header
   */
  public function __construct($message = "", $code = 0, \Exception $previous = NULL, $data = '', $hmac_header = '') {
    parent::__construct($message, $code, $previous);
  }

  /**
   * @return mixed
   */
  public function getData() {
    return $this->data;
  }

  /**
   * @return string
   */
  public function getHmacHeader() {
    return $this->hmac_header;
  }
}
