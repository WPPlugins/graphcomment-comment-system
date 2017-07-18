<?php

class GcApiService
{

  /**
   * Generic report http error
   * @param $url string the url (maybe a comment with it)
   * @param null $httpCode integer httpCode
   */
  private static function reportHttpError($url, $httpCode = NULL) {
    $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,2);
    $caller = isset($dbt[1]['function']) ? $dbt[1]['function'] : 'A function';
    $log = $caller.' - Got HTTP ret !== 200 or empty body ( url: ' . $url . ' )';
    if ($httpCode) {
      $log = $log . ', httpCode -> ' . $httpCode;
    }
    GcLogger::getLogger()->error($log);
  }

  /**
   * Call the GC api to init an importation.
   *
   * @param $nbrComment integer The number of comment to import.
   *
   * @return array['error'] string The message if an error occurred else `false`.
   * @return array['batch_number'] string The batch number use in the following import.
   */
  public static function importInit($nbrComment) {

    $res = wp_remote_post(
        constant('API_URL_IMPORT_INIT'),
        array(
            'sslverify' => constant('SSLVERIFY'),
            'headers' => array(
                'Authorization' => 'Bearer ' . GcParamsService::getInstance()->graphcommentGetClientToken()
            ),
            'body' => array(
                'public_key' => GcParamsService::getInstance()->graphcommentGetWebsite(),
                'platform' => 'wp',
                'total' => $nbrComment
            )
        )
    );

    // Test the HTTP ret code
    $httpCode = wp_remote_retrieve_response_code($res);
    if ($httpCode === 401) {
      self::reportHttpError(constant('API_URL_IMPORT_INIT'), $httpCode);
      GcParamsService::getInstance()->graphcommentOAuthReLog(true);
      return array('error' => true, 'batch_import_number' => NULL);
    }
    else if ($httpCode !== 200) {
      self::reportHttpError(constant('API_URL_IMPORT_INIT'), $httpCode);

      // The caller function should handle the error printing
      $body = json_decode(wp_remote_retrieve_body($res));
      if ($body->msg === 'import already in progress') {
        self::reportHttpError(constant('API_URL_IMPORT_INIT').' -> import already in progress', $httpCode);
        return array('error' => __('Import Already Pending', 'graphcomment-comment-system'), 'batch_number' => NULL);
      }
      return array('error' => __('Error Import Init', 'graphcomment-comment-system'), 'batch_number' => NULL);
    }

    GcLogger::getLogger()->debug('GcImportService::importInit() - Everything is ok');

    // Extract the body
    $res = json_decode(wp_remote_retrieve_body($res));

    return array('error' => false, 'batch_number' => $res->batch_import_number);
  }

  /**
   * Call the GC api to cancel an importation
   *
   * @param $public_key string The website's public key
   * @param $batch_number string The batch import number
   * @return array['error'] string The message if an error occurred else `false`.
   */
  public static function importCancel($public_key, $batch_number) {
    $res = wp_remote_post(
        constant('API_URL_IMPORT_STOP'),
        array(
            'sslverify' => constant('SSLVERIFY'),
            'body' => array(
                'public_key' => $public_key,
                'platform' => 'wp',
                'batch_import_number' => $batch_number
            )
        )
    );
    // Extract the HTTP ret code
    $httpCode = wp_remote_retrieve_response_code($res);

    if ($httpCode !== 200) {
      self::reportHttpError(constant('API_URL_IMPORT_STOP'), $httpCode);

      // The caller function should handle the error printing
      return array('error' => __('Error Import Stop', 'graphcomment-comment-system'));
    }

    return array('error' => false);
  }

  /**
   * Call the GC api to import more comments
   *
   * @param $batch_number string the import batch number
   * @param $comments array The comments to import
   * @return array['error'] string The message if an error we can manage occurred
   *                                           else if error `true`
   *                                           else `false` if all right.
   */
  public static function pushImportComments($batch_number, $comments) {
    // Send comments to API
    $res = wp_remote_post(constant('API_URL_IMPORT'),
        array('sslverify' => constant('SSLVERIFY'),
            'body' => array('public_key' => get_option('gc_public_key', ''),
                'platform' => 'wp',
                'batch_import_number' => $batch_number,
                'comments' => $comments)));

    // Extract the HTTP ret code
    $httpCode = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);

    if ($httpCode !== 200) {
      self::reportHttpError(constant('API_URL_IMPORT').' - (body: '.$body.') - ', $httpCode);
      if (is_wp_error($res)) {
        $log = 'GcImportService::importNextComments() - is_wp_error(res): ' . $res->get_error_message();
        GcLogger::getLogger()->error($log);
      }

      if (get_option(GcImportService::$options_prefix . 'status_stopped') === 'true') {
        return array('error' => true);
      }

      // Error importation
      return array('error' => __('Error Import', 'graphcomment-comment-system'));
    }
    // If the import was canceled, just stop importing comments
    else if ($body === 'import canceled') {
      self::reportHttpError(constant('API_URL_IMPORT').' - (body: '.$body.') - ', $httpCode);
      return array('error' => true);
    }

    // Everything's fine
    return array('error' => false);
  }

  /**
   * Call the GC api to finish the importation
   *
   * @param $public_key string The website's public key
   * @param $batch_number string The batch import number
   * @return array['error'] boolean `true` if an error occurred else `false`.
   */
  public static function importFinish($public_key, $batch_number) {
    $res = wp_remote_post(
        constant('API_URL_IMPORT_FINISH'),
        array(
            'sslverify' => constant('SSLVERIFY'),
            'body' => array(
                'public_key' => $public_key,
                'platform' => 'wp',
                'batch_import_number' => $batch_number
            )
        )
    );

    // Extract the HTTP ret code
    $httpCode = wp_remote_retrieve_response_code($res);

    if ($httpCode !== 200) {
      self::reportHttpError(constant('API_URL_IMPORT_FINISH'), $httpCode);
      // Error importation
      return array('error' => true);
    }

    return array('error' => false);
  }

  /**
   * Call the GC api to get all the new comments in their threads from imported key.
   *
   * @param $gc_website_id String The website id.
   * @param $gc_sync_key String The synchronization key.
   * @param $gc_sync_date_from DateTime The date of the last synchronisation.
   *
   * @return array['error'] boolean If an error occur.
   * @return array['threads'] array List of threads.
   */
  public static function getNewComments($gc_website_id, $gc_sync_key, $gc_sync_date_from)
  {
    // Build the request
    $body = array('platform' => 'wp', 'private_key' => $gc_sync_key);
    // Add the 'date_from' if has to
    if ($gc_sync_date_from !== false) {
      $body['date_from'] = strtotime($gc_sync_date_from);
    }

    // Send the request
    $url = constant('API_URL') . '/website/' . $gc_website_id . '/sync';
    $request = wp_remote_post($url, array('sslverify' => constant('SSLVERIFY'), 'body' => $body));

    // Handle response and body
    $httpCode = wp_remote_retrieve_response_code($request);
    $body = json_decode(wp_remote_retrieve_body($request));

    if ($httpCode !== 200 || $body === NULL) {
      self::reportHttpError($url, $httpCode);
      // Error while getting the comments
      update_option('gc-sync-error', json_encode(array('content' => __('Error Getting Sync', 'graphcomment-comment-system'))));

      GcLogger::getLogger()->error('Get sync error');

      return array('error' => true, 'threads' => NULL);
    }

    // Extract the thread objects
    return array('error' => false, 'threads' => $body->threads);
  }

  /**
   * @param $gc_public_key
   * @return array
   */
  public static function setupNotif($gc_public_key) {
    $url = constant('API_URL_SETUP_NOTIF');
    $request = wp_remote_post(
        $url,
        array(
            'sslverify' => constant('SSLVERIFY'),
            'headers' => array(
                'Authorization' => 'Bearer ' . GcParamsService::getInstance()->graphcommentGetClientToken()
            ),
            'body' => array(
                'public_key' => $gc_public_key,
                'platform' => 'wp'
            )
        )
    );

    // Extract the HTTP ret code and HTTP body
    $httpCode = wp_remote_retrieve_response_code($request);
    $body = wp_remote_retrieve_body($request);

    // Token not authorize anymore
    if ($httpCode === 401) {
      self::reportHttpError($url, $httpCode);
      return array('error' => 401);
    }

    // An error happened
    if ($httpCode !== 200) {
      self::reportHttpError($url, $httpCode);
      return array('error' => 'Error Setup Notif');
    }

    $ret = json_decode($body);

    // Bad JSON received, should never happened
    if (!$ret) {
      self::reportHttpError($url.' - Bad json received - '.$body, $httpCode);
      return array('error' => 'Error Setup Notif');
    }

    // Didn't received what expected
    if (!isset($ret->website_id) || !isset($ret->wp_notif_key)) {
      self::reportHttpError($url.' - Wrong values received', $httpCode);
      return array('error' => 'Unknown Error');
    }

    // Everything is good
    GcLogger::getLogger()->debug('GcNotifController::handleOptionForm() - Activating the notifications locally');
    return array('error' => false, 'website_id' => $ret->website_id, 'wp_notif_key' => $ret->wp_notif_key);
  }
}