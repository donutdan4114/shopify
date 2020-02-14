<?php

namespace Shopify;

use Psr\Http\Message\ResponseInterface;
use function GuzzleHttp\Psr7\parse_query;

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
   * @var string
   */
  private $next_page_params;

  /**
   * The query params for the previous page request including the "page_info" param.
   *
   * @var string
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
  private $last_response;

  /**
   * Whether we should be using pagination for the current request.
   *
   * The first request does not use pagination. Subsequent requests will need it.
   *
   * @var bool
   */
  private $cur_page_num = 0;

  /**
   * PaginatedResponse constructor.
   *
   * @param string $resource
   * @param \Shopify\Client $client
   * @param \Psr\Http\Message\ResponseInterface $response
   */
  public function __construct(Client $client, $resource, array $opts = []) {
    $this->client = $client;
    $this->resource = $resource;
    $this->opts = $opts;
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
    return $this->last_response;
  }

  /**
   * Get the next page of results.
   *
   * @return array|object
   */
  public function getNextPage() {
    $opts = $this->opts;

    if ($this->cur_page_num > 0) {
      // Once we're past the first page we MUST override the query params.
      $opts['query'] = $this->next_page_params;
    }

    if ($this->hasNextPage()) {
      $result = $this->client->get($this->resource, $opts);
      $this->last_response = $this->client->getLastResponse();
      $this->cur_page_num++;
      $this->setDefaults();
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

    if ($this->cur_page_num > 0) {
      // Once we're past the first page we MUST override the query params.
      $opts['query'] = $this->prev_page_params;
    }

    if ($this->hasPrevPage()) {
      $result = $this->client->get($this->resource, $opts);
      $this->last_response = $this->client->getLastResponse();
      $this->cur_page_num--;
      $this->setDefaults();
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
    return !empty($this->next_page_url) || $this->cur_page_num === 0;
  }

  /**
   * Check if there is a previous page.
   *
   * @return bool
   */
  public function hasPrevPage() {
    return !empty($this->prev_page_url) && $this->cur_page_num > 0;
  }

  /**
   * Set default properties.
   */
  protected function setDefaults() {
    $link_header = $this->last_response->getHeader('Link')[0];
    $this->next_page_url = $this->getLinkHeaderUrl($link_header, 'next');
    $this->prev_page_url = $this->getLinkHeaderUrl($link_header, 'previous');
    $this->next_page_params = $this->parseUrlParams($this->next_page_url);
    $this->prev_page_params = $this->parseUrlParams($this->prev_page_url);
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
    return parse_query($query_params);
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
