<?php

/**
 * @file
 * Contains an metafields-aware shopify client.
 */

namespace Shopify;

use Shopify\Client;

/**
 * An enhanced shopify client.
 *
 * This is a decorator class essentialy,
 * could be used to add metafields-awareness to
 * an existing client object, specifically to the
 * getProducts and getProduct methods.
 */
class MetafieldsClient extends Client {

  /**
   * The Shopify private or public app client.
   *
   * @var Shopify\Client
   */
  protected $client;

  /**
   * Constructor for the enhanced shopify app client.
   *
   * @param Shopify\Client $client
   *   Shopify app client.
   */
  public function __construct(Client $client) {
    $this->client = $client;
  }

  /**
   * Gets all products incl. their metafields matching the filter query.
   *
   * @param array $opts
   *   Options to pass to the request.
   *
   * @return array
   *   Returns an array of products (stdClass objects) each containing a
   *   metafields property, with all the metafields attached to that product
   *   in Shopify.
   */
  public function getProducts(array $opts = []) {
    $productSearchResult = $this->client->getResources('products', $opts);
    foreach ($productSearchResult as $productIdx => $product) {
      $metafieldSearchResult = $this->client->get('products/' . $product->id . '/metafields', []);
      $product->metafields = $metafieldSearchResult->metafields;
      $productSearchResult[$productIdx] = $product;
    }
    return $productSearchResult;
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
   *   Returns a product (stdClass object) containing a metafields property,
   *   with all the metafields attached to that product in Shopify.
   */
  public function getProduct($id, array $fields = []) {
    $product = $this->client->getProduct($id, $fields);
    $metafieldSearchResult = $this->client->get('products/' . $product->id . '/metafields', []);
    $product->metafields = $metafieldSearchResult->metafields;
    return $product;
  }

}
