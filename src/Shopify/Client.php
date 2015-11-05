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
   */
  public function __construct($shop_domain, $api_key, $password, $shared_secret) {
    $this->shop_domain = $shop_domain;
    $this->password = $password;
    $this->shared_secret = $shared_secret;
    $this->api_key = $api_key;
    $this->client = new GuzzleHttp\Client(['base_uri' => $this->getApiUrl()]);
  }

  /**
   * Makes a request to the Shopify API.
   *
   * @param string $method
   *   HTTP Method, either GET, POST, PUT, DELETE.
   * @param string $resource
   *   Shopify resource. Such as shop, products, customers, orders, etc.
   * @param array $opts
   *   Options to pass to the request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Returns a Response object.
   *
   * @throws \Shopify\Exception
   */
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
      throw new \Shopify\Exception(print_r($this->errors, TRUE), $this->last_response->getStatusCode(), $e, $this);
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

  /**
   * Perform a GET request to the Shopify API.
   *
   * @param string $resource
   *   Shopify resource.
   * @param array $opts
   *   Additional options to pass to the request.
   *
   * @return object|array
   *   Returns the Shopify API response JSON decoded.
   *
   * @see \Shopify\Client::request()
   */
  public function get($resource, array $opts = []) {
    return json_decode($this->request('GET', $resource, $opts)
      ->getBody()
      ->getContents());
  }

  /**
   * Perform a POST request to the Shopify API.
   *
   * @param string $resource
   *   Shopify resource.
   * @param object|array $data
   *   Data to JSON encode and send to the API.
   * @param array $opts
   *   Additional options to pass to the request.
   *
   * @return object|array
   *   Returns the Shopify API response JSON decoded.
   *
   * @see \Shopify\Client::request()
   */
  public function post($resource, $data, array $opts = []) {
    $opts['json'] = $data;
    return json_decode($this->request('POST', $resource, $opts)
      ->getBody()
      ->getContents());
  }

  /**
   * Perform a PUT request to the Shopify API.
   *
   * @param string $resource
   *   Shopify resource.
   * @param object|array $data
   *   Data to JSON encode and send to the API.
   * @param array $opts
   *   Additional options to pass to the request.
   *
   * @return object|array
   *   Returns the Shopify API response JSON decoded.
   *
   * @see \Shopify\Client::request()
   */
  public function put($resource, $data, array $opts = []) {
    $opts['json'] = $data;
    return json_decode($this->request('PUT', $resource, $opts)
      ->getBody()
      ->getContents());
  }

  /**
   * Perform a DELETE request to the Shopify API.
   *
   * @param string $resource
   *   Shopify resource.
   * @param array $opts
   *   Additional options to pass to the request.
   *
   * @return object|array
   *   Returns the Shopify API response JSON decoded.
   *
   * @see \Shopify\Client::request()
   */
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

  /**
   * Gets the last response object.
   *
   * @return \Psr\Http\Message\ResponseInterface
   */
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

  // API abstraction functions.

  /**
   * Gets shop info.
   *
   * @param array $fields
   *   Specific fields to return.
   *
   * @return object
   */
  public function getShopInfo(array $fields = []) {
    $opts['query']['fields'] = implode(',', $fields);
    return $this->get('shop', $opts)->shop;
  }

  /**
   * Get a specific product.
   *
   * @param int $id
   *   Product ID.
   * @param array $fields
   *   Specific product fields to return.
   *
   * @return object
   */
  public function getProduct($id, array $fields = []) {
    $opts['query']['fields'] = implode(',', $fields);
    return $this->get('products/' . $id, $opts)->product;
  }

  /**
   * Count products based on passed filters.
   *
   * @param array $filters
   *   Filters.
   *
   * @return int
   */
  public function getProductsCount(array $filters = []) {
    $opts['query'] = $filters;
    return (int) $this->get('products/count', $opts)->count;
  }

  /**
   * Creates a product.
   *
   * @param array $product
   *   A valid Shopify product.
   *
   * @return object
   */
  public function createProduct(array $product = []) {
    $data = ['product' => $product];
    return $this->post('products', $data)->product;
  }

  /**
   * Updates a specific product with new params.
   *
   * @param int $id
   *   Product ID.
   * @param array $update_product
   *   New product values.
   *
   * @return object
   */
  public function updateProduct($id, array $update_product = []) {
    $data['product'] = $update_product;
    return $this->put('products/' . $id, $data)->product;
  }

  /**
   * Deletes a specific product.
   *
   * @param int $id
   *   Product ID to delete.
   *
   * @return object
   */
  public function deleteProduct($id) {
    return $this->delete('products/' . $id);
  }

}
