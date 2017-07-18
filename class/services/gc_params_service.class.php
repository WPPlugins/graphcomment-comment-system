<?php

/**
 * Class GcParamsService
 * Handle the params of all GraphComment plugin
 *
 *   gc_activated {Boolean} =>
 *   gc_activated_all {Boolean} =>
 *   gc_activated_from {Date} =>
 *
 *   gc_public_key {String} => The website public key
 *   gc_website_id {String} => The website id
 *
 *   gc_oauth_client_key {String} =>
 *   gc_oauth_client_secret {String} =>
 *   gc_oauth_client_token {String} => The OAuth
 *   gc_oauth_redirect_uri {String} => The Oauth redirect uri
 */

class GcParamsService
{
  private static $_instance = null;
  private $gc_params_default = null;
  private $gc_websites = null;

  private $gc_user = null;

  private $gc_params = array(
    'gc_activated' => null,
    'gc_activated_all' => null,
    'gc_activated_from' => null,

    'gc_public_key' => null,
    'gc_website_id' => null,

    'gc_oauth_client_key' => null,
    'gc_oauth_client_secret' => null,
    'gc_oauth_client_token' => null,
    'gc_oauth_client_code' => null,
    'gc_oauth_redirect_uri' => null,

    'gc_debug_activated' => null
  );

  /**
   * Singleton Function
   */
  public static function getInstance()
  {
    if (is_null(self::$_instance)) {
      self::$_instance = new GcParamsService();
    }
    return self::$_instance;
  }

  private function initDefaultArray()
  {
    $this->gc_params_default = array(
      'gc_oauth_redirect_uri' => admin_url('options.php?graphcomment_oauth_code=true'),
      //'gc_public_key' => null,
      //'gc_website_id' => null,
      //'gc_oauth_client_code' => null,
      //'gc_oauth_client_token' => null,

      //'gc_import_date_begin' => null,
      //'gc_import_date_end' => null,
      //'gc_import_batch_number' => null,
      //'gc_import_status' => null,
      //'gc_import_total' => null,
      //'gc_import_nbr_comment_import' => null,
    );
  }

  private function setAllDefaultValues()
  {
    foreach ($this->gc_params_default as $gc_param_default_name => $gc_param_default_value) {
      if (is_null($gc_param_default_value)) {
        delete_option($gc_param_default_name);
      } else {
        update_option($gc_param_default_name, $gc_param_default_value);
      }
    }
  }

  private function initUser() {
    $response = wp_remote_get(constant('API_URL_ME'), array('sslverify' => constant('SSLVERIFY'), 'headers' => array('Authorization' => 'Bearer ' . $this->graphcommentGetClientToken())));

    // Extract the HTTP ret code
    $httpCode = wp_remote_retrieve_response_code($response);
    $response = wp_remote_retrieve_body($response);

    // The token is no longer valid
    if ($httpCode === 401) {
      GcLogger::getLogger()->error('GcParamsService::initUser() - Got HTTP ret 401 -> renew token ( url: '.constant('API_URL_ME').' )');

      // Ask for a new one
      return $this->graphcommentRenewToken();
    }
    else if ($httpCode === 200){
      $this->gc_user = json_decode($response);
    }
  }

  private function __construct()
  {
    $this->initDefaultArray();

    $this->setAllDefaultValues();

    foreach ($this->gc_params as $gc_param_name => &$gc_param_value) {
      $gc_param_value = get_option($gc_param_name);
    }

    if ($this->graphcommentOAuthIsLogged()) {
      $this->initUser();
    }
    else {
      return $this->graphcommentRenewToken();
    }
  }

  /*
   * Begin OAuth Functions
   */
  public function graphcommentOAuthIsLogged()
  {
    return !empty($this->gc_params['gc_oauth_client_key']) && !empty($this->gc_params['gc_oauth_client_secret']) && !empty($this->gc_params['gc_oauth_client_code']) && !empty($this->gc_params['gc_oauth_client_token']);
  }

  public function graphcommentOAuthReLog($removeAllCredentials = false, $redirect = false)
  {
    if ($removeAllCredentials) {
      $this->graphcommentDeleteClientCode();
      $this->graphcommentDeleteClientToken();
      $this->graphcommentDeleteWebsite(true, true);
    }
    if ($redirect) {
      update_option('graphcomment-disconnect', 'true');
      return wp_redirect(admin_url('admin.php?page=graphcomment'));
    }
  }

  public function graphcommentOAuthInitConnection()
  {
    // Client already configured
    if (!empty($this->gc_params['gc_oauth_client_key']) && !empty($this->gc_params['gc_oauth_client_secret'])) {

      // Create the client
      $response = wp_remote_get(constant('API_URL_OAUTH_CLIENT_ME'), array(
        'sslverify' => constant('SSLVERIFY'),
        'headers' => array('Authorization' => 'Basic ' . base64_encode($this->gc_params['gc_oauth_client_key'] . ':' . $this->gc_params['gc_oauth_client_secret']))
      ));

      // Extract the HTTP ret code
      $httpCode = wp_remote_retrieve_response_code($response);

      if ($httpCode !== 200) {
        GcLogger::getLogger()->error('GcParamsService::graphcommentOAuthInitConnection() - Got HTTP ret !== 200 -> createNewApplication ( url: '.constant('API_URL_OAUTH_CLIENT_ME').' )');

        $this->createNewApplication();
      }
    }
    else {
      $this->createNewApplication();
    }

    return true;
  }

  public function createNewApplication() {
    // Create the client
    $response = wp_remote_post(constant('API_URL_OAUTH_CLIENT_CREATE'), array('sslverify' => constant('SSLVERIFY')));

    // Extract the HTTP ret code
    $httpCode = wp_remote_retrieve_response_code($response);
    $response = wp_remote_retrieve_body($response);

    if ($httpCode !== 200) {
      GcLogger::getLogger()->error('GcParamsService::createNewApplication() - Got HTTP ret !== 200 ( url: '.constant('API_URL_OAUTH_CLIENT_CREATE').' )');

      // The caller function should handle the error printing
      return __('Error while requesting the API to create the client');
    }
    $response = json_decode($response);

    update_option('gc_oauth_client_key', $response->clientKey);
    $this->gc_params['gc_oauth_client_key'] = $response->clientKey;
    update_option('gc_oauth_client_secret', $response->clientSecret);
    $this->gc_params['gc_oauth_client_secret'] = $response->clientSecret;
  }

  public function graphcommentGetClientKey()
  {
    return $this->gc_params['gc_oauth_client_key'];
  }

  public function graphcommentGetClientSecret()
  {
    return $this->gc_params['gc_oauth_client_secret'];
  }

  private function graphcommentSetClientToken($token)
  {
    if (!$token) return;
    update_option('gc_oauth_client_token', $token);
    $this->gc_params['gc_oauth_client_token'] = $token;
  }
  public function graphcommentGetClientToken()
  {
    return $this->gc_params['gc_oauth_client_token'];
  }
  public function graphcommentDeleteClientToken()
  {
    delete_option('gc_oauth_client_token');
    $this->gc_params['gc_oauth_client_token'] = null;
  }

  public function graphcommentGetRedirectUri()
  {
    return $this->gc_params['gc_oauth_redirect_uri'];
  }

  private function graphcommentSetClientCode($code)
  {
    if (!$code) return;
    update_option('gc_oauth_client_code', $code);
    $this->gc_params['gc_oauth_client_code'] = $code;
  }

  public function graphcommentGetClientCode()
  {
    return $this->gc_params['gc_oauth_client_code'];
  }

  public function graphcommentDeleteClientCode()
  {
    delete_option('gc_oauth_client_code');
    $this->gc_params['gc_oauth_client_code'] = null;
  }

  public function graphcommentRenewToken() {

    $code = $this->graphcommentGetClientCode();

    if (empty($code)) {
      return $this->graphcommentOAuthReLog(true);
    }

    $this->graphcommentDeleteClientToken();

    // Adk for a token
    $response = wp_remote_post(constant('API_URL_OAUTH_CLIENT_CREATE_TOKEN'),
      array('sslverify' => constant('SSLVERIFY'),
          'headers' => array('Authorization' => 'Basic ' . base64_encode($this->graphcommentGetClientKey() . ':' . $this->graphcommentGetClientSecret())),
          'body' => array('code' => $this->graphcommentGetClientCode(),
          'redirect_uri' => $this->graphcommentGetRedirectUri(),
          'grant_type' => 'authorization_code')
      )
    );
    // Extract the HTTP ret code
    $httpCode = wp_remote_retrieve_response_code($response);
    $response = wp_remote_retrieve_body($response);

    if ($httpCode !== 200) {
      GcLogger::getLogger()->error('GcParamsService::graphcommentDeleteClientToken() - Got HTTP ret !== 200 ( url: '.constant('API_URL_OAUTH_CLIENT_CREATE_TOKEN').' )');

      // The caller function should handle the error printing
      $this->graphcommentOAuthReLog(true);
      return __('Error during connection');
    }
    if ($httpCode === 200) {
      $response = json_decode($response);

      $this->graphcommentSetClientToken($response->access_token);
    }
    return true;
  }

  public function graphcommentCreateOauthToken($code)
  {
    if (!$code) return;

    $this->graphcommentSetClientCode($code);

    $this->graphcommentRenewToken();

    return true;
  }

  public function graphcommentApiIsUp() {
    return ($this->gc_user !== null);
  }
  public function graphcommentApiIsDown() {
    return ($this->gc_user === null);
  }
  /*
   * End OAuth Functions
   */

  /*
   * Begin Website Functions
   */
  private function graphcommentInitWebsites()
  {
    if (is_null($this->gc_websites)) {

      // Create the client
      $response = wp_remote_get(
          constant('API_URL_GET_WEBSITES'),
          array('sslverify' => constant('SSLVERIFY'),
                'headers' => array('Authorization' => 'Bearer ' . $this->graphcommentGetClientToken())
          )
      );

      // Extract the HTTP ret code
      $httpCode = wp_remote_retrieve_response_code($response);
      $response = wp_remote_retrieve_body($response);

      if ($httpCode === 401) {
        GcLogger::getLogger()->error('GcParamsService::graphcommentInitWebsites() - Got HTTP ret === 401 ( url: '.constant('API_URL_GET_WEBSITES').' )');

        // Ask for a new one
        if (!$this->graphcommentRenewToken()) {
          // The code is no longer valid, restart the whole connection process
          return $this->graphcommentOAuthReLog(true);
        }
      } else if ($httpCode !== 200) {
        GcLogger::getLogger()->error('GcParamsService::graphcommentInitWebsites() - Got HTTP ret  ( url: '.constant('API_URL_GET_WEBSITES').' )');

        // The caller function should handle the error printing
        return __('Error while requesting the Websites in the API');
      }
      $this->gc_websites = json_decode($response);
    }
  }

  public function graphcommentGetNbrWebsites()
  {
    $this->graphcommentInitWebsites();
    return count($this->gc_websites);
  }

  public function graphcommentSelectOnlyWebsite() {
    $this->graphcommentInitWebsites();
    if ($this->graphcommentGetNbrWebsites() === 1) {
      $this->graphcommentSetWebsite($this->gc_websites[0]->public_key);
    }
  }

  public function graphcommentHasWebsites()
  {
    $this->graphcommentInitWebsites();
    return $this->graphcommentGetNbrWebsites() > 0;
  }

  public function graphcommentGetWebsites()
  {
    $this->graphcommentInitWebsites();
    return $this->gc_websites;
  }

  public function graphcommentIsWebsiteChoosen()
  {
    return !empty($this->gc_params['gc_public_key']);
  }

  public function graphcommentGetWebsite()
  {
    return $this->gc_params['gc_public_key'];
  }

  public function graphcommentGetWebsiteId()
  {
    return $this->gc_params['gc_website_id'];
  }

  public function graphcommentDeleteWebsite($deletePublicKey, $deleteWebsiteId) {
    if ($deletePublicKey) {
      delete_option('gc_public_key');
      $this->gc_params['gc_public_key'] = null;
    }
    if ($deleteWebsiteId) {
      delete_option('gc_website_id');
      $this->gc_params['gc_website_id'] = null;
    }
  }

  public function graphcommentIsWebsiteValid($website_public_key)
  {
    $this->graphcommentInitWebsites();
    return count(array_filter($this->gc_websites, function ($website) use ($website_public_key) {
      return isset($website->public_key) && ($website->public_key === $website_public_key);
    })) > 0;
  }

  public function graphcommentSetWebsite($website_public_key)
  {
    $this->graphcommentInitWebsites();
    if (!$this->graphcommentIsWebsiteValid($website_public_key)) {
      return __('Wrong website');
    }
    update_option('gc_public_key', $website_public_key);
    $this->gc_params['gc_public_key'] = $website_public_key;
    $website_id = $this->getWebsiteIdFromPublicKey($website_public_key);
    update_option('gc_website_id', $website_id);
    $this->gc_params['gc_website_id'] = $website_id;
    $this->graphcommentSetActivationAll('true');
    return true;
  }

  private function getWebsiteIdFromPublicKey($website_public_key) {
    $this->graphcommentInitWebsites();
    return array_shift(array_filter($this->gc_websites, function ($website) use ($website_public_key) {
      return isset($website->public_key) && ($website->public_key === $website_public_key);
    }))->_id;
  }
  /*
   * End Websites Functions
   */


  /*
   * Begin User Functions
   */

  /**
   * @param $field string The name of the user property
   * @return string The user property or '' if not found
   */
  public function getUserField($field) {
    if (!isset($field) || !isset($this->gc_user) || !isset($this->gc_user->{$field})) return '';
    if ($field === 'picture') {
      return $this->generateUserPicture();
    }
    return $this->gc_user->{$field};
  }

  private function generateUserPicture() {
    if (strpos($this->gc_user->{'picture'}, '/images/avatar_3.png') === false) {
      return substr($this->gc_user->{'picture'}, 0, strlen('http')) === 'http' ? $this->gc_user->{'picture'} : constant('GRAPHCOMMENT_URL') . $this->gc_user->{'picture'};
    }
    return constant('GRAPHCOMMENT_URL') . '/images/avatar_3.png';
  }

  /*
   * End UserFunctions
   */

  /*
   * Begin General GraphComment Activation Functions
   */
  public function graphcommentIsActivated()
  {
    return $this->gc_params['gc_activated'] === 'true';
  }

  private function graphcommentSetActivation($value)
  {
    if ($value === 'true') {
      update_option('gc_activated', $value);
      $this->gc_params['gc_activated'] = $value;
    } else if ($value === 'false') {
      delete_option('gc_activated');
      $this->gc_params['gc_activated'] = null;
    }
  }

  private function graphcommentSetActivationAll($value)
  {
    $this->graphcommentSetActivation($value);
    if ($value === 'true') {
      update_option('gc_activated_all', $value);
      $this->gc_params['gc_activated_all'] = $value;
    } else if ($value === 'false') {
      delete_option('gc_activated_all');
      $this->gc_params['gc_activated_all'] = null;
    }
  }

  private function graphcommentSetActivationFrom($date)
  {
    update_option('gc_activated_from', $date);
    $this->gc_params['gc_activated_from'] = $date;
  }

  private function graphcommentDeleteActivatedFrom()
  {
    delete_option('gc_activated_from');
    $this->gc_params['gc_activated_from'] = null;
  }

  public function graphcommentUpdateSEOFriendly($seoActivated) {
    update_option('gc_seo_activated', $seoActivated);
    $this->gc_params['gc_seo_activated'] = $seoActivated;
  }

  public function graphcommentActivate()
  {
    $this->graphcommentSetActivation('true');
  }

  public function graphcommentDeactivate()
  {
    $this->graphcommentSetActivationAll('false');
  }

  public function graphcommentActivateAll()
  {
    $this->graphcommentSetActivationAll('true');
    $this->graphcommentDeleteActivatedFrom();
  }

  public function graphcommentActivateFrom($date)
  {
    $this->graphcommentSetActivationAll('false');
    $this->graphcommentSetActivation('true');
    $this->graphcommentSetActivationFrom($date);
  }
  /*
   * End General GraphComment Activation Functions
   */

  /*
   * Begin Identifier Functions
   */
  public function graphcommentIdenfitierGetPostTitle($post) {
    return get_the_title($post);
  }

  public function graphcommentIdenfitierGetPostUrl($post) {
    return get_permalink($post);
  }

  public function graphcommentIdentifierGenerate($post) {
    $newPost = false;
    if ($post instanceof stdClass) {
      $newPost = $post;
    }
    else {
      $newPost = get_post($post);
    }
    return $newPost->post_name;
  }
  /*
   * End Identifier Functions
   */



  /*
   * Begin debug functions
   */
  public function graphcommentDebugChange() {
    if ($this->graphcommentDebugIsActivated()) {
      $this->graphcommentDebugDeactivate();
    }
    else {
      $this->graphcommentDebugActivate();
    }
  }

  public function graphcommentDebugActivate()
  {
    update_option('gc_debug_activated', 'true');
    $this->gc_params['gc_debug_activated'] = 'true';
  }

  public function graphcommentDebugDeactivate()
  {
    delete_option('gc_debug_activated');
    $this->gc_params['gc_debug_activated'] = null;
  }

  public function graphcommentDebugIsActivated()
  {
    return $this->gc_params['gc_debug_activated'] === 'true';
  }

  /*
   * End debug functions
   */
}