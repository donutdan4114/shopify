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
abstract class Client {

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
   * Default limit number of resources to fetch from Shopify.
   * @var int
   */
  public $default_limit = 50;

  /**
   * Default headers to pass into every request.
   * @var array
   */
  public $default_headers = [];

  /**
   * Delays the next API call. Set by the rate limiter.
   * @var bool
   */
  protected static $delay_next_call = FALSE;


  /**
   * The last response from the API.
   * @var ResponseInterface
   */
  protected $last_response;

  protected $has_errors = FALSE;
  protected $errors = FALSE;

  protected $client_type;
  protected $shop_domain;
  protected $password;
  protected $shared_secret;
  protected $api_key;
  protected $client;
  protected $call_limit;
  protected $call_bucket;

  protected function getNewHttpClient(array $opts = []) {
    return new GuzzleHttp\Client($opts);
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
   * @throws \Shopify\ClientException
   */
  public function request($method, $resource, array $opts = []) {
    if ($this->fetch_as_json && !isset($opts['headers']['Accept'])) {
      $opts['headers']['Accept'] = 'application/json';
    }

    $opts['headers'] = array_merge($opts['headers'], $this->default_headers);

    if ($this->rate_limit && self::$delay_next_call) {
      // Sleep a random amount of time to help prevent bucket overflow.
      usleep(rand(3, 10) * 1000000);
    }

    try {
      $this->last_response = $this->client->request($method, $resource . '.json', $opts);
    } catch (GuzzleHttp\Exception\RequestException $e) {
      $this->last_response = $e->getResponse();
      if (!empty($this->last_response)) {
        $this->has_errors = TRUE;
        $this->errors = json_decode($this->last_response->getBody()
          ->getContents())->errors;
        throw new ClientException(print_r($this->errors, TRUE), $this->last_response->getStatusCode(), $e, $this);
      }
      else {
        throw new ClientException('Request failed.', 0, $e, $this);
      }
    }

    $this->has_errors = FALSE;
    $this->errors = FALSE;

    $this->setCallLimitParams();

    if ($this->callLimitReached()) {
      self::$delay_next_call = TRUE;
    }
    else {
      self::$delay_next_call = FALSE;
    }

    return $this->last_response;
  }

  /**
   * Sets call limit params from the Shopify header.
   */
  protected function setCallLimitParams() {
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
    return $this->getCallLimit() >= 0.8;
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
  protected function getApiUrl() {
    return strtr(self::URL_FORMAT, [
      '{api_key}' => $this->api_key,
      '{password}' => $this->password,
      '{shop_domain}' => $this->shop_domain,
    ]);
  }

  /**
   * Parses an incoming webhook and validates it.
   *
   * @param bool $validate
   *   Whether the webhook data should be validated or not.
   * @param string $data
   *   JSON data to parse. Data we be pulled from php://input if not provided.
   * @param string $hmac_header
   *   Shopify HMAC header. Default will be pulled from $_SERVER.
   * @return object
   *   Shopify webhook data.
   *
   * @throws \Shopify\ClientException
   */
  public function getIncomingWebhook($validate = TRUE, $data = '', $hmac_header = '') {
    $webhook = new IncomingWebhook($this->shared_secret);
    if ($validate) {
      try {
        $webhook->validate($data, $hmac_header);
      } catch (WebhookException $e) {
        throw new ClientException('Invalid webhook: ' . $e->getMessage(), 0, $e, $this);
      }
    }
    return $webhook->getData();
  }

  /**
   * Calculates the HMAC based on Shopify's specification.
   *
   * @param string $data
   *   JSON data.
   * @param string $secret
   *   Shopify shared secret.
   *
   * @return string
   */
  public function calculateHmac($data, $secret) {
    return hash_hmac('sha256', $data, $secret, FALSE);
  }

  /**
   * Allows you to iterate over paginated resources.
   * This will return a single resource at a time to the iterator.
   *
   * @code
   * foreach ($client->getResourcePager('products', 5) as $product) {
   *   // API will fetch 5 products at a time.
   *   // This will update ALL products in the store.
   *   $client->updateProduct(['title' => $product->title . ' updated']);
   * }
   * @endcode
   *
   * @param string $resource
   *   Shopify resource.
   * @param int $limit
   *   Limit resources returned per request. If not set, the default_limit will be used.
   * @param array $opts
   *   Additional options to pass to the request. Note: You don't need to set the page/limit.
   *
   * @return \Generator
   */
  public function getResourcePager($resource, $limit = NULL, array $opts = []) {
    $current_page = 1;
    if (!isset($opts['query']['limit'])) {
      $opts['query']['limit'] = ($limit ?: $this->default_limit);
    }
    while (TRUE) {
      $opts['query']['page'] = $current_page;
      $result = $this->get($resource, $opts);
      if (empty($result)) {
        break;
      }
      foreach (get_object_vars($result) as $resource_name => $results) {
        if (empty($results)) {
          return;
        }
        foreach ($results as $object) {
          yield $object;
        }
        if (count($results) < $opts['query']['limit']) {
          // Passing "page" # to Shopify doesn't always implement pagination.
          return;
        }
        $current_page++;
      }
    }
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
   * Gets all resources matching the query from the server.
   *
   * This will fetch ALL resources from the server. May use multiple requests
   * depending on the number of items being returned.
   *
   * @param string $resource
   *   Shopify resource.
   * @param array $opts
   *   Additional options.
   *
   * @return array
   *   Returns all resource items as an array.
   */
  public function getResources($resource, array $opts = []) {
    $items = [];
    foreach ($this->getResourcePager($resource, NULL, $opts) as $item) {
      $items[] = $item;
    }
    return $items;
  }

  /**
   * Loads a Shopify resource by it's ID.
   *
   * @param string $resource
   *   Shopify resource.
   * @param int $id
   *   Shopify resource ID.
   * @param array $fields
   *   Array if field query params.
   *
   * @return mixed
   */
  public function getResourceById($resource, $id, array $fields = []) {
    $opts['query']['fields'] = implode(',', $fields);
    $result = $this->get($resource . '/' . $id, $opts);
    return reset($result);
  }

  /**
   * Updates a given resource by it's ID and returns it.
   *
   * @param string $resource
   *   Shopify resource.
   * @param int $id
   *   Shopify resource ID.
   * @param array $data
   *   Update data array.
   *
   * @return mixed
   */
  public function updateResource($resource, $id, array $data = []) {
    $result = $this->put($resource . '/' . $id, $data);
    return reset($result);
  }

  /**
   * Deletes a Shopify resource.
   *
   * @param string $resource
   *   Shopify resource.
   *   Shopify resource ID.
   * @param array $opts
   *   Options to pass to the query.
   *
   * @return array|object|NULL
   */
  public function deleteResource($resource, $id, array $opts = []) {
    return $this->delete($resource . '/' . $id, $opts);
  }

  /**
   * Gets the count of the resource based on the passed filters.
   *
   * @param string $resource
   *   Shopify resource.
   * @param array $filters
   *   Array of filters.
   *
   * @return int
   *   Returns the count.
   */
  public function getResourceCount($resource, array $filters = []) {
    $opts['query'] = $filters;
    return (int) $this->get($resource . '/count', $opts)->count;
  }

  /**
   * Creates a resource and returns it's full data.
   *
   * @param string $resource
   *   Shopify resource.
   * @param array $data
   *   Shopify resource data.
   *
   * @return mixed
   */
  public function createResource($resource, array $data = []) {
    $result = $this->post($resource, $data);
    return reset($result);
  }

  /**
   * Gets all products matching the filter query
   *
   * @param array $opts
   *   Options to pass to the request.
   *
   * @return array
   *   Array of products.
   */
  public function getProducts(array $opts = []) {
    return $this->getResources('products', $opts);
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
    return $this->getResourceById('products', $id, $fields);
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
    return $this->getResourceCount('products', $filters);
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
    return $this->createResource('products', $data);
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
    return $this->updateResource('products', $id, $data);
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
    return $this->deleteResource('products', $id);
  }

}
