<?php
namespace Examples;

use Shopify;

require_once '../vendor/autoload.php';
require_once '../credentials.php';

header('Content-Type: application/json');

$client = new Shopify\Client($shop_domain, $api_key, $password, $shared_secret);

$result = $client->get('shop');

print json_encode($result);