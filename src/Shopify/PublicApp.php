<?php

namespace Shopify;

use GuzzleHttp\Exception\ClientException;

/**
 * Class PublicApp
 *
 * @package Shopify
 *
 * Used for creating Public Apps that can be authenticated through the API and
 * requires and access_token.
 */
class PublicApp extends Client {

  const AUTHORIZE_URL_FORMAT = 'https://{shop_domain}/admin/oauth/authorize?client_id={api_key}&scope={scopes}&redirect_uri={redirect_uri}&state={state}';

  const ACCESS_TOKEN_URL_FORMAT = 'https://{shop_domain}/admin/oauth/access_token';

  private $access_token = '';

  private $state;

  private $code;

  private $params;

  /**
   * Public app credentials.
   * See: [your-domain].myshopify.com/admin/apps/private
   *
   * @param string $shop_domain
   *   Shopify domain.
   * @param string $api_key
   *   Shopify API Key.
   * @param string $password
   *   Shopify API Password.
   * @param string $shared_secret
   *   Shopify API Shared Secret.
   * @param array $opts
   *   Default options to set.
   */
  public function __construct($shop_domain, $api_key, $shared_secret, array $opts = []) {
    $this->shop_domain = $shop_domain;
    $this->shared_secret = $shared_secret;
    $this->api_key = $api_key;
    $this->client_type = 'public';

    if (isset($opts['version'])) {
      $this->version = $opts['version'];
      unset($opts['version']);
    }

    $opts['base_uri'] = $this->getApiUrl();
    $this->client = $this->getNewHttpClient($opts);
  }

  /**
   * Sets the state that is used to validate authorization requests.
   *
   * @param string $state
   *   State to compare.
   */
  public function setState($state) {
    $this->state = $state;
  }

  /**
   * Sets the active access token to be used in authenticated requests.
   *
   * @param string $token
   *   Shopify access token.
   */
  public function setAccessToken($token) {
    $this->access_token = $token;
    $this->default_headers['X-Shopify-Access-Token'] = $this->access_token;
  }

  /**
   * Returns the active access token or fetches a new one.
   *
   * @param bool $refresh
   *   Whether to refresh the token from the server. Token will be refreshed from
   *   the server if an existing access_token doesn't exist.
   *
   * @return bool|string
   *   Returns the access token or FALSE if one couldn't be retrieved.
   */
  public function getAccessToken($refresh = FALSE) {
    if (!$refresh && isset($this->access_token) && !empty($this->access_token)) {
      return $this->access_token;
    }
    if (!$this->validateInstall()) {
      return FALSE;
    }
    // Okay to get the access token.
    $data = [
      'client_id' => $this->api_key,
      'client_secret' => $this->shared_secret,
      'code' => $this->code,
    ];
    $domain_path = strtr(self::ACCESS_TOKEN_URL_FORMAT, ['{shop_domain}' => $this->shop_domain]);
    try {
      $response = $this->client->request('POST', $domain_path, [
        'headers' => ['Accept' => 'application/json'],
        'form_params' => $data,
      ]);
    } catch (ClientException $e) {
      return FALSE;
    }
    $contents = $response->getBody()->getContents();
    $contents = json_decode($contents);
    return $contents->access_token;
  }

  /**
   * Validates the app was installed correctly by ensuring the state and HMAC are correct.
   *
   * @param array $params
   *   Params to check with, by default will be $_GET params.
   *
   * @return bool
   *   Returns TRUE if it is safe to continue with API requests.
   */
  public function validateInstall(array $params = []) {
    if (empty($params)) {
      $params = $_GET;
    }
    if (empty($params['state'])) {
      $this->state = '';
    }
    if (!empty($this->state) && $this->state !== $params['state']) {
      return FALSE;
    }
    if (!$this->hmacSignatureValid($params)) {
      return FALSE;
    }
    $this->params = $params;
    $this->code = $this->params['code'];
    return TRUE;
  }

  /**
   * Checks that the request HMAC is valid.
   *
   * @param array $params
   *   Params to check in the HMAC.
   *
   * @return bool
   *   Returns TRUE if the HMAC is valid.
   *
   * @link https://docs.shopify.com/api/authentication/oauth#verification @endlink
   */
  public function hmacSignatureValid(array $params = []) {
    $original_hmac = $params['hmac'];
    unset($params['hmac']);
    unset($params['signature']);
    ksort($params);
    $data = http_build_query($params);
    return ($original_hmac === $this->calculateHmac($data, $this->shared_secret));
  }

  /**
   * Creates the authorization URL and can automatically forward to the URL.
   *
   * @param array $scopes
   * @param string $redirect_uri
   * @param string $state
   * @param bool $automatically_redirect
   *
   * @return string
   */
  public function authorizeUser($redirect_uri, array $scopes, $state, $automatically_redirect = TRUE) {
    $url = $this->formatAuthorizeUrl($this->shop_domain, $this->api_key, $scopes, $redirect_uri, $state);
    if ($automatically_redirect) {
      header("Location: $url");
    }
    return $url;
  }

  /**
   * Builds the authorization URL from passed params.
   */
  private function formatAuthorizeUrl($shop_domain, $api_key, $scopes, $redirect_uri, $state) {
    return strtr(self::AUTHORIZE_URL_FORMAT, [
      '{shop_domain}' => $shop_domain,
      '{api_key}' => $api_key,
      '{scopes}' => implode(',', $scopes),
      '{redirect_uri}' => urlencode($redirect_uri),
      '{state}' => $state,
    ]);
  }
}
