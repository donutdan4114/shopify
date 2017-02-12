# Shopify PHP SDK
A simple Shopify PHP SDK for private apps to easily interact with the Shopify API.  
[![Travis Build Status](https://travis-ci.org/donutdan4114/shopify.svg?branch=master)](https://travis-ci.org/donutdan4114/shopify)

[Shopify API Documentation](https://docs.shopify.com/api) | [Packagist](https://packagist.org/packages/donutdan4114/shopify) | [Build Status](https://travis-ci.org/donutdan4114/shopify)

Features include:  

* ability to easily GET, PUT, POST and DELETE resources
* process and validate incoming webhooks
* automatic rate limiting to avoid API calls from erroring

## Setup/Installation
Uses [guzzlehttp/guzzle](https://packagist.org/packages/guzzlehttp/guzzle).
You can include this library by running:  
`composer require donutdan4114/shopify`

## Private & Public Apps
You can use this library for private or public app creation. Using private apps is easier because their is no `access_token` required.
However, if you want to create a publicly accessible app, you must use the Public App system.

### Private App
Simply instantiate a private app with the `shop_domain`, `api_key`, `password`, and `shared_secret`.
```php
$client = new Shopify\PrivateApp($SHOPIFY_SHOP_DOMAIN, $SHOPIFY_API_KEY, $SHOPIFY_PASSWORD, $SHOPIFY_SHARED_SECRET);
$result = $client->get('shop');
```
### Public App
You must first setup a public app. [View documentation](https://docs.shopify.com/api/introduction/getting-started).
You need an authorization URL.

```php
session_start();
$client = new Shopify\PublicApp($_GET['shop'], $APP_API_KEY, $APP_SECRET);

// You set a random state that you will confirm later.
$random_state = 'client-id:' . $_SESSION['client_id'];

$client->authorizeUser('[MY_DOMAIN]/redirect.php', [
  'read_products',
  'write_products',
], $random_state);
```

At this point, the user is taken to their store to authorize the application to use their information.  
If the user accepts, they are taken to the redirect URL.

```php
session_start();
$client = new Shopify\PublicApp($_GET['shop'], $APP_API_KEY, $APP_SECRET);

// Used to check request data is valid.
$client->setState('client-id:' . $_SESSION['client_id']);

if ($token = $client->getAccessToken()) {
  $_SESSION['shopify_access_token'] = $token;
  $_SESSION['shopify_shop_domain'] = $_GET['shop'];
  header("Location: dashboard.php");
}
else {
  die('invalid token');
}

```

It's at this point, in **dashboard.php** you could starting doing API request by setting the `access_token`.

```php
session_start();
$client = new Shopify\PublicApp($_SESSION['shopify_shop_domain'], $APP_API_KEY, $APP_SECRET);
$client->setAccessToken($_SESSION['shopify_access_token']);
$products = $client->getProducts();
```
  
---

## Methods
### GET
Get resource information from the API.
```php
$client = new Shopify\PrivateApp($SHOPIFY_SHOP_DOMAIN, $SHOPIFY_API_KEY, $SHOPIFY_PASSWORD, $SHOPIFY_SHARED_SECRET);
$result = $client->get('shop');
```
`$result` is a JSON decoded `stdClass`:
```
object(stdClass)#33 (1) {
  ["shop"]=>
  object(stdClass)#31 (44) {
    ["id"]=>
    int([YOUR_SHOP_ID])
    ["name"]=>
    string(15) "[YOUR_SHOP_NAME]"
    ["email"]=>
    string(22) "[YOUR_SHOP_EMAIL]"
    ["domain"]=>
    string(29) "[YOUR_SHOP_DOMAIN]"
    ...
  }
}
```
Get product IDs by passing query params:
```php
$result = $client->get('products', ['query' => ['fields' => 'id']]);
foreach($result->products as $product) {
  print $product->id;
}
```

### POST
Create new content with a POST request.
```php
$data = ['product' => ['title' => 'my new product']];
$result = $client->post('products', $data);
```

### PUT
Update existing content with a given ID.
```php
$data = ['product' => ['title' => 'updated product name']];
$result = $client->put('products/' . $product_id, $data);
```

### DELETE
Easily delete resources with a given ID.
```php
$client->delete('products/' . $product_id);
```

## Simple Wrapper
To make it easier to work with common API resources, there are several short-hand functions.
```php
// Get shop info.
$shop_info = $client->getShopInfo();

// Get a specific product.
$product = $client->getProduct($product_id);

// Delete a specific product.
$client->deleteProduct($product_id);

// Create a product.
$product = $client->createProduct(['title' => 'my new product']);

// Count products easily.
$count = $client->getProductsCount(['updated_at_min' => time() - 3600]);

// Easily get all products without having to worry about page limits.
$products = $client->getProducts();
// This will fetch all products and will make multiple requests if necessary.
// You can easily supply filter arguments.
$products = $client->getProducts(['query' => ['vendor' => 'MY_VENDOR']]);

// For ease-of-use, you should use the getResources() method to automatically handle Shopify's pagination.
$orders = $client->getResources('orders', ['query' => ['fields' => 'id,billing_address,customer']]);
// This will ensure that if there are over 250 orders, you get them all returned to you.

// If efficiency and memory limits are a concern,  you can loop over results manually.
foreach ($this->client->getResourcePager('products', 25) as $product) {
  // Fetches 25 products at a time.
  // If you have 500 products, this will create 20 separate requests for you.
  // PHP memory will only be storing 25 products at a time, which keeps thing memory-efficient.
}
```

## Parsing Incoming Webhooks
If you have a route setup on your site to accept incoming Shopify webhooks, you can easily parse the data and validate the contents.
There are two ways to validate webhooks: manually, or using the client.

```php
// Process webhook manually.
$webhook = new Shopify\IncomingWebhook($SHOPIFY_SHARED_SECRET);
try {
  $webhook->validate();
  $data = $webhook->getData();
} catch (Shopify\WebhookException $e) {
  // Errors means you should not process the webhook data.
  error_log($e->getMessage());
}

// Process webhook using the $client.
try {
  $data = $client->getIncomingWebhook($validate = TRUE);
} catch (Shopify\ClientException $e) {
  error_log($e->getMessage());
}
if (!empty($data)) {
  // Do something with the webhook data.
}
```

## Error Handling
Any API error will throw an instance of `Shopify\ClientException`.
```php
try {
  $response = $client->put('products/BAD_ID');
} catch (Shopify\ClientException $e) {
  // Get request errors.
  error_log($e->getErrors());
  // Get last response object.
  $last_response = $e->getLastResponse();
  $code = $e->getCode();
  $code = $last_response->getStatusCode();
}
```

## API Limit Handling
This class can handle API rate limiting for you based on Shopify's "leaky bucket" algorithm.  
It will automatically slow down requests to not hit the rate limiter.  
You can disabled this with:  
```php
$client->rate_limit = FALSE;
```
You can put in your own rate limiting logic using the `$client->getCallLimit()` and `$client->callLimitReached()` methods.

## Testing
Tests can be run with `phpunit`.  
Since the tests actually modify the connected store, you must explicitly allow tests to be run by settings `SHOPIFY_ALLOW_TESTS` environment variable to `TRUE`.  
Without that, you will be get a message like:
```
Shopify tests cannot be run.
Running Shopify tests will delete all connected store info.
Set environment variable SHOPIFY_ALLOW_TESTS=TRUE to allow tests to be run.
```
You can also test the API on [RapidAPI](https://rapidapi.com/package/Shopify/functions?utm_source=ShopifyGitHub&utm_medium=button).
