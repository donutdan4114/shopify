<?php

class ProductTest extends PHPUnit_Framework_TestCase {

  /**
   * @var \Shopify\Client
   */
  private $client;

  public function setUp() {
    $this->client = new Shopify\Client(getenv('SHOPIFY_SHOP_DOMAIN'), getenv('SHOPIFY_API_KEY'), getenv('SHOPIFY_PASSWORD'), getenv('SHOPIFY_SHARED_SECRET'));
  }

  public function testProductPost() {
    $product = [
      'title' => 'test product 1',
      'body_html' => 'test product <strong>body html</strong>',
    ];
    $product = $this->client->createProduct($product);
    $this->assertFalse($this->client->hasErrors(), 'Client has errors');
    $this->assertNotEmpty($product, 'Product response is empty');
    $this->productGet($product->id);
  }

  public function testBadProductPost() {
    $product = ['missing_title' => TRUE];
    try {
      $response = $this->client->createProduct($product);
    } catch (\Shopify\ClientException $e) {
      $this->assertEquals("can't be blank", $e->getErrors()->title[0]);
    }
  }

  public function testProductPut() {
    $product = [
      'title' => 'test product 2',
    ];
    $product = $this->client->createProduct($product);
    $update_product = ['title' => 'test product 2 UPDATED'];
    $product = $this->client->updateProduct($product->id, $update_product);
    $this->assertFalse($this->client->hasErrors(), 'Client has errors');
    $this->assertEquals('test product 2 UPDATED', $product->title, 'Product title is not updated');
  }

  private function productGet($id) {
    $product = $this->client->getProduct($id);
    $this->assertFalse($this->client->hasErrors());
    $this->assertNotEmpty($product, 'Product response is empty');
    $this->assertEquals('test product 1', $product->title, 'Product title does not match');
    $this->assertEquals('test product <strong>body html</strong>', $product->body_html, 'Product body_html does not match');
  }

  /**
   * @depends testProductPost
   */
  public function testProductDelete() {
    $response = $this->client->get('products', ['query' => ['fields' => 'id']]);
    foreach ($response->products as $product) {
      $response = $this->client->deleteProduct($product->id);
      $this->assertFalse($this->client->hasErrors(), 'Client has errors');
    }
  }

  /**
   * @depends testProductDelete
   */
  public function testAllProductsDeleted() {
    $response = $this->client->get('products', ['query' => ['fields' => 'id']]);
    $this->assertEmpty($response->products, 'Not all products were deleted');
  }

}