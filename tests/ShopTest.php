<?php

class ShopTest extends PHPUnit_Framework_TestCase {

  /**
   * @var \Shopify\Client
   */
  private $client;

  public function setUp() {
    $this->client = new Shopify\Client(getenv('SHOPIFY_SHOP_DOMAIN'), getenv('SHOPIFY_API_KEY'), getenv('SHOPIFY_PASSWORD'), getenv('SHOPIFY_SHARED_SECRET'));
  }

  public function testShopGetInfo() {
    $response = $this->client->get('shop');
    $this->assertObjectHasAttribute('shop', $response);
    $shop = $response->shop;
    $this->assertObjectHasAttribute('domain', $shop);
    $this->assertObjectHasAttribute('myshopify_domain', $shop);
    $this->assertObjectHasAttribute('email', $shop);
    $this->assertObjectHasAttribute('name', $shop);
    $this->assertNotEmpty($shop->domain);
    $this->assertNotEmpty($shop->myshopify_domain);
    $this->assertNotEmpty($shop->email);
    $this->assertNotEmpty($shop->name);
  }

}