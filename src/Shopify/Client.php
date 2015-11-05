<?php
namespace Shopify;

use GuzzleHttp;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Client
 *
 * Creates a Shopify Client that can interact with the Shopify API.
 *
 * @package Shopify
 */
class Client {

  /**
   * Shopify API URL format.
   */
  const URL_FORMAT = 'https://{api_key}:{password}@{shop_domain}/admin/';

  /**
   * Shopify call limit header.
   */
  const CALL_LIMIT_HEADER = 'http_x_shopify_shop_api_call_limit';

  /**
   * Fetches data from the API as JSON.
   * @var bool
   */
  public $fetch_as_json = TRUE;

  /**
   * Rate limits API calls so we don't hit Shopify's rate limiter.
   * @var bool
   */
  public $rate_limit = TRUE;

  /**
   * Delays the next API call. Set by the rate limiter.
   * @var bool
   */
  private $delay_next_call = FALSE;

  /**
   * The last response from the API.
   * @var ResponseInterface
   */
  private $last_response;

  private $has_errors = FALSE;
  private $errors = FALSE;

  private $shop_domain;
  private $password;
  private $shared_secret;
  private $api_key;
  private $client;
  private $call_limit;
  private $call_bucket;

  public function __construct($shop_domain, $api_key, $password, $shared_secret) {
    $this->shop_domain = $shop_domain;
    $this->password = $password;
    $this->shared_secret = $shared_secret;
    $this->api_key = $api_key;
    $this->client = new GuzzleHttp\Client(['base_uri' => $this->getApiUrl()]);
  }

  public function request($method, $resource, array $opts = []) {
    if ($this->fetch_as_json) {
      $opts['headers']['Accept'] = 'application/json';
    }

    if ($this->rate_limit && $this->delay_next_call) {
      // Sleep a random amount of time to help prevent bucket overflow.
      usleep(rand(1, 3) * 1000000);
    }

    try {
      $this->last_response = $this->client->request($method, $resource . '.json', $opts);
    } catch (GuzzleHttp\Exception\RequestException $e) {
      $this->last_response = $e->getResponse();
      $this->has_errors = TRUE;
      $this->errors = json_decode($this->last_response->getBody()
        ->getContents())->errors;
      return $this->last_response;
    }

    $this->has_errors = FALSE;
    $this->errors = FALSE;

    $this->setCallLimitParams();

    if ($this->callLimitReached()) {
      $this->delay_next_call = TRUE;
    }
    else {
      $this->delay_next_call = FALSE;
    }

    return $this->last_response;
  }

  /**
   * Sets call limit params from the Shopify header.
   */
  private function setCallLimitParams() {
    $limit_parts = explode('/', $this->last_response->getHeader(self::CALL_LIMIT_HEADER)[0]);
    $this->call_limit = $limit_parts[0];
    $this->call_bucket = $limit_parts[1];
  }

  public function get($resource, array $opts = []) {
    return json_decode($this->request('GET', $resource, $opts)
      ->getBody()
      ->getContents());
  }

  public function post($resource, $data, array $opts = []) {
    $opts['json'] = $data;
    return json_decode($this->request('POST', $resource, $opts)
      ->getBody()
      ->getContents());
  }

  public function put($resource, $data, array $opts = []) {
    $opts['json'] = $data;
    return json_decode($this->request('PUT', $resource, $opts)
      ->getBody()
      ->getContents());
  }

  public function delete($resource, array $opts = []) {
    return json_decode($this->request('DELETE', $resource, $opts)
      ->getBody()
      ->getContents());
  }

  /**
   * Checks if the last request produced errors.
   *
   * @return bool
   *   Returns TRUE if the last request had errors.
   */
  public function hasErrors() {
    return $this->has_errors;
  }

  /**
   * Gets errors from the last response.
   *
   * @return array
   *   Array of errors.
   */
  public function getErrors() {
    return $this->errors;
  }

  public function getLastResponse() {
    return $this->last_response;
  }

  /**
   * Determines if the call limit has been reached.
   *
   * @return bool
   *   Returns TRUE if the call limit has been reached.
   */
  public function callLimitReached() {
    return $this->getCallLimit() === 1;
  }

  /**
   * Determines the call limit.
   *
   * If result is < 1, limit has not been reached.
   * If result == 1, limit has been reached.
   *
   * @return float
   *   Call limit as ratio decimal.
   */
  public function getCallLimit() {
    return $this->call_limit / $this->call_bucket;
  }

  /**
   * Builds the API URL from the client settings.
   *
   * @return string
   *   API URL.
   */
  private function getApiUrl() {
    return strtr(self::URL_FORMAT, [
      '{api_key}' => $this->api_key,
      '{password}' => $this->password,
      '{shop_domain}' => $this->shop_domain,
    ]);
  }

}
