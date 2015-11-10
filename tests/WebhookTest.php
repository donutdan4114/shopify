<?php

class WebhookTest extends PHPUnit_Framework_TestCase {

  /**
   * @var \Shopify\Client
   */
  private $client;
  private $data;
  private $hmac_header;

  public function setUp() {
    if (!getenv('SHOPIFY_ALLOW_TESTS')) {
      print 'Shopify tests cannot be run.' . PHP_EOL;
      print 'Running Shopify tests will delete all connected store info.' . PHP_EOL;
      print 'Set environment variable SHOPIFY_ALLOW_TESTS=TRUE to allow tests to be run.' . PHP_EOL;
      print PHP_EOL;
      exit;
    }
    $this->client = new Shopify\PrivateApp(getenv('SHOPIFY_SHOP_DOMAIN'), getenv('SHOPIFY_API_KEY'), getenv('SHOPIFY_PASSWORD'), getenv('SHOPIFY_SHARED_SECRET'));
    $this->data = '{"test":"this is a test"}';
    $this->hmac_header = base64_encode(hash_hmac('sha256', $this->data, getenv('SHOPIFY_SHARED_SECRET'), TRUE));
  }

  /**
   * Validates that the HMAC function calculates correctly.
   *
   * Does not parse a real Shopify webhook.
   */
  public function testValidateIncomingWebhook() {
    $webhook = new \Shopify\IncomingWebhook(getenv('SHOPIFY_SHARED_SECRET'));
    $result = $webhook->validate($this->data, $this->hmac_header);
    $this->assertTrue($result, 'Webhook error.');
    $this->assertNotEmpty($webhook->getData(), 'Data is empty.');
    $this->assertNotEmpty($webhook->getRawData(), 'Data is empty.');
    $this->assertEquals($this->data, $webhook->getRawData(), 'Parsed data is not equal to the original data.');
  }

  public function testValidateClientWebhook() {
    $data = $this->client->getIncomingWebhook(TRUE, $this->data, $this->hmac_header);
    $this->assertNotEmpty($data, 'Webhook error.');
    $this->assertObjectHasAttribute('test', $data);
    $this->assertEquals('this is a test', $data->test, 'Data has changed.');
  }

}