# Shopify PHP SDK
A simple Shopify PHP SDK for private apps to easily interact with the Shopify API.  
![Travis Build Status](https://travis-ci.org/donutdan4114/shopify.svg?branch=master)

[Shopify API Documentation](https://docs.shopify.com/api)

## Methods
### GET
Get resource information from the API.
```php
$client = new Shopify\Client($SHOPIFY_SHOP_DOMAIN, $SHOPIFY_API_KEY, $SHOPIFY_PASSWORD, $SHOPIFY_SHARED_SECRET);
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

## Error Handling
Any request that produces an error will set `$client->hasErrors()` to `TRUE`.  
And will put the errors in `$client->getErrors()` as an array.  
Example:
```php
$response = $client->put('products/BAD_ID');
if ($client->hasErrors()){
  log($client->getErrors();
  return;
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
