<?php

namespace Shopify;

use GuzzleHttp\Psr7\Query;
use Psr\Http\Message\ResponseInterface;

/**
 * Class PaginatedResponse
 *
 * https://shopify.dev/tutorials/make-paginated-requests-to-rest-admin-api
 *
 * Helps manage responses using cursor-based pagination.
 */
class PaginatedResponse {

  /**
   * @var \Shopify\Client
   */
  private $client;

  /**
   * The request opts.
   *
   * @var array
   */
  private $opts;

  /**
   * The next page URL.
   *
   * @var string
   */
  private $next_page_url;

  /**
   * The previous page URL.
   *
   * @var string
   */
  private $prev_page_url;

  /**
   * The query params for the next page request including the "page_info" param.
   *
   * @var array
   */
  private $next_page_params;

  /**
   * The query params for the previous page request including the "page_info" param.
   *
   * @var array
   */
  private $prev_page_params;

  /**
   * The resource type we are paginating.
   *
   * @var string
   */
  private $resource;

  /**
   * The last response from the client.
   *
   * @var ResponseInterface
   */
  private $response;

  /**
   * PaginatedResponse constructor.
   *
   * @param \Shopify\Client $client
   * @param string $resource
   * @param array $opts
   */
  public function __construct(Client $client, $resource, array $opts = []) {
    $this->client = $client;
    $this->response = $client->getLastResponse();
    $this->resource = $resource;
    $this->opts = $opts;
    $this->setDefaults();
  }

  /**
   * Returns the Shopify Client.
   *
   * @return \Shopify\Client
   */
  public function getClient() {
    return $this->client;
  }

  /**
   * Get the last response.
   *
   * @return \Psr\Http\Message\ResponseInterface
   */
  public function getLastResponse() {
    return $this->response;
  }

  /**
   * Get the next page of results.
   *
   * @return array|object
   */
  public function getNextPage() {
    if ($this->hasNextPage()) {
      $opts = $this->opts;
      $opts['query'] = $this->next_page_params;
      $result = $this->client->get($this->resource, $opts);
      return $result;
    }
    else {
      return [];
    }
  }

  /**
   * Get the previous page of results.
   *
   * @return array|object
   */
  public function getPrevPage() {
    $opts = $this->opts;
    $opts['query'] = $this->prev_page_params;

    if ($this->hasPrevPage()) {
      $result = $this->client->get($this->resource, $opts);
      return $result;
    }
    else {
      return [];
    }
  }

  /**
   * Check if there is a next page.
   *
   * @return bool
   */
  public function hasNextPage() {
    return !empty($this->next_page_url);
  }

  /**
   * Check if there is a previous page.
   *
   * @return bool
   */
  public function hasPrevPage() {
    return !empty($this->prev_page_url);
  }

  /**
   * Get the next page params.
   *
   * @return array
   */
  public function getNextPageParams() {
    return $this->next_page_params;
  }

  /**
   * Get the previous page params.
   *
   * @return array
   */
  public function getPrevPageParams() {
    return $this->prev_page_params;
  }

  /**
   * Set default properties.
   */
  protected function setDefaults() {
    if ($this->response instanceof ResponseInterface && !empty($this->response->getHeader('Link')[0])) {
      $link_header = $this->response->getHeader('Link')[0];
      $this->next_page_url = $this->getLinkHeaderUrl($link_header, 'next');
      $this->prev_page_url = $this->getLinkHeaderUrl($link_header, 'previous');
      $this->next_page_params = $this->parseUrlParams($this->next_page_url);
      $this->prev_page_params = $this->parseUrlParams($this->prev_page_url);
    }
  }

  /**
   * Parse the URL query params we'll need for subsequent requests.
   *
   * @param string $url
   *
   * @return array
   */
  protected function parseUrlParams($url) {
    if (empty($url)) {
      return [];
    }
    $query_params = parse_url($url, PHP_URL_QUERY);
    parse_str(parse_url($url, PHP_URL_QUERY), $query_params);
    return Query::build($query_params);
  }

  /**
   * Get the a "prev" or "next" URL from the Link header value.
   *
   * @param string $link_header
   * @param string $link_name
   *
   * @return string
   */
  protected function getLinkHeaderUrl($link_header, $link_name) {
    $matches = [];
    preg_match("/<(.[^>]*)>; rel=\"{$link_name}\"/", $link_header, $matches);
    return isset($matches[1]) ? $matches[1] : NULL;
  }
}
