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
      'product' => [
        'title' => 'test product 1',
        'body_html' => 'test product <strong>body html</strong>',
      ],
    ];
    $response = $this->client->post('products', $product);
    $this->assertFalse($this->client->hasErrors(), 'Client has errors');
    $product = $response->product;
    $this->assertNotEmpty($product, 'Product response is empty');
    $this->productGet($product->id);
  }

  public function testBadProductPost() {
    $product = ['product' => ['missing_title' => TRUE]];
    $response = $this->client->post('products', $product);
    $this->assertTrue($this->client->hasErrors());
    $this->assertNotEmpty($this->client->getErrors());
    $this->assertEquals("can't be blank", $this->client->getErrors()->title[0]);
  }

  public function testProductPut() {
    $product = [
      'product' => [
        'title' => 'test product 2',
      ],
    ];
    $response = $this->client->post('products', $product);
    $update_product = ['product' => ['title' => 'test product 2 UPDATED']];
    $response = $this->client->put('products/' . $response->product->id, $update_product);
    $this->assertFalse($this->client->hasErrors(), 'Client has errors');
    $this->assertEquals('test product 2 UPDATED', $response->product->title, 'Product title is not updated');
  }

  private function productGet($id) {
    $response = $this->client->get('products/' . $id);
    $this->assertFalse($this->client->hasErrors());
    $product = $response->product;
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
      $response = $this->client->delete('products/' . $product->id);
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