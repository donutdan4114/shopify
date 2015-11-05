<?php
namespace Examples;

use Shopify;

require_once '../vendor/autoload.php';
require_once '../credentials.php';

//header('Content-Type: application/json');

$client = new Shopify\Client($shop_domain, $api_key, $password, $shared_secret);

$result = $client->get('products', ['query' => ['fields' => 'id']]);
print json_encode($result);
return;

$product = [
  'product' => [
    'title' => 'test product 1',
    'body_html' => 'testing body html',
    'description' => 'testing description',
  ]
];

$result = $client->post('products', $product);
$result = $client->delete('products/' . $result->product->id);

print json_encode($result);