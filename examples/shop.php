<?php
namespace Examples;

use Shopify;

require_once '../vendor/autoload.php';

header('Content-Type: application/json');

$client = new Shopify\Client(getenv('SHOPIFY_SHOP_DOMAIN'), getenv('SHOPIFY_API_KEY'), getenv('SHOPIFY_PASSWORD'), getenv('SHOPIFY_SHARED_SECRET'));

$result = $client->get('shop');

print json_encode($result);