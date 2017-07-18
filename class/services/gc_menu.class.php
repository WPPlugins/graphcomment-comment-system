<?php

/**
 * Class GcMenu
 * Usage :
 */

if (!class_exists('GcMenu')) {
  class GcMenu
  {
    /**
     * Create the class and bind create menu method to the wp action named <$action_name>
     * @param $action_name string the wp action hook
     */
    public function __construct($action_name)
    {
      // Bind the create_menu to the <$action_name>
      add_action($action_name, array($this, 'create_menu'));
    }

    private function get_notif_str($nbr, $id = false) {
      return '<span id="' . ($id !== false ? $id : '') . '" class="update-plugins count-' . $nbr . '"><span class="plugin-count">' . $nbr . '</span></span>';
    }

    /**
     * Adds a GraphComment button to the WordPress menu
     */
    public function create_menu()
    {
      // Create new top-level menu handling the notifications

      // Get the notifications
      $this->get_notif();

      $GraphCommentMain = false;
      $GraphCommentNotif = false;
      $GraphCommentSync = false;

      $nbrNewComments = get_option('gc_notif_comments');
      // Do we have a sync error ?
      $gc_sync_error = get_option('gc-sync-error');
      if ($nbrNewComments !== false) {
        $GraphCommentNotif = intval($nbrNewComments);
      }
      if ($gc_sync_error !== false) {
        $GraphCommentSync = 1;
      }

      if ($_SERVER['QUERY_STRING'] !== 'page=graphcomment' && $_SERVER['QUERY_STRING'] !== 'page=settings') {
        $GraphCommentMain = $GraphCommentNotif + $GraphCommentSync;
      }


      if (!GcParamsService::getInstance()->graphcommentOAuthIsLogged()) {
        // Print a notif
        $GraphCommentMain = 1;
        add_menu_page(__('GC Plugin Admin Page Title', 'graphcomment-comment-system'), __('GC Plugin Admin Menu Title', 'graphcomment-comment-system') . ' ' . $this->get_notif_str($GraphCommentMain), 'manage_options', 'graphcomment', '_graphcomment_settings_page_admin', plugins_url('../../theme/images/icon.png', __FILE__));

        $page_url = admin_url('admin.php');
        $page_query = 'page=graphcomment';
        if ($_SERVER['QUERY_STRING'] === 'page=settings' && !isset($_POST['gc_action'])) {
          // Fake menu, just to avoid error during redirection
          add_submenu_page('graphcomment', __('GC Plugin Settings Page Title', 'graphcomment-comment-system'), __('GC Plugin Settings SubMenu Title', 'graphcomment-comment-system') . ' ' . $this->get_notif_str($GraphCommentMain), 'manage_options', 'settings', '_graphcomment_settings_page');
          wp_redirect($page_url . '?' . $page_query);
        }

      } else {
        // Print the menus normally
        add_menu_page(__('GC Plugin Admin Page Title', 'graphcomment-comment-system'), __('GC Plugin Admin Menu Title', 'graphcomment-comment-system') . ' ' . $this->get_notif_str($GraphCommentMain), 'manage_options', 'graphcomment', '_graphcomment_settings_page_admin', plugins_url('../../theme/images/icon.png', __FILE__));
        add_submenu_page('graphcomment', __('GC Plugin Admin Page Title', 'graphcomment-comment-system'), __('GC Plugin Admin SubMenu Title', 'graphcomment-comment-system') . ' ' . $this->get_notif_str($GraphCommentNotif, 'submenu_notif_new_comments'), 'manage_options', 'graphcomment');
        add_submenu_page('graphcomment', __('GC Plugin Settings Page Title', 'graphcomment-comment-system'), __('GC Plugin Settings SubMenu Title', 'graphcomment-comment-system') . $this->get_notif_str($GraphCommentSync), 'manage_options', 'settings', '_graphcomment_settings_page');

        // If the user has no website, He will have to create one
        if (!GcParamsService::getInstance()->graphcommentHasWebsites()) {
          GcLogger::getLogger()->debug('GcMenu::create_menu() - User has no website, has to create one');

          if (GcParamsService::getInstance()->graphcommentApiIsUp()) {
            $page_url = admin_url('admin.php');
            $page_query = 'page=graphcomment';
            if ($_SERVER['QUERY_STRING'] === 'page=settings' && !isset($_POST['gc_action'])) {
              wp_redirect($page_url . '?' . $page_query);
              exit ;
            }
          }
        }
        else if (!GcParamsService::getInstance()->graphcommentIsWebsiteChoosen()) {
          if ($_SERVER['QUERY_STRING'] === 'page=graphcomment') {
            if (get_option('gc_create_website') !== 'true') {
              wp_redirect(admin_url('admin.php?page=settings'));
              exit ;
            }
            else {
              delete_option('gc_create_website');
            }
          }
        }
      }

      // Handle the disconnection
      if (isset($_GET['graphcomment-disconnect']) && $_GET['graphcomment-disconnect'] === 'true') {
        GcLogger::getLogger()->debug('GcMenu::create_menu() - User wants to disconnect');

        // Just add the submenu to prevent error
        add_submenu_page('graphcomment',
            __('GC Plugin Settings Page Title', 'graphcomment-comment-system'),
            __('GC Plugin Settings SubMenu Title', 'graphcomment-comment-system'),
            'manage_options', 'settings', '_graphcomment_settings_page');
        return GcParamsService::getInstance()->graphcommentOAuthReLog(true, true);
      }

      return null;
    }

    /**
     * Check the notification and print the pin on the menu if there's one.
     */
    public function get_notif() {
      $gc_public_key = get_option('gc_public_key');
      $gc_website_id = get_option('gc_website_id');
      $wp_notif_key = get_option('gc_notif_private_key');
      $gc_notif_last_date = get_option('gc_notif_last_date');

      if ($gc_public_key === false || $gc_website_id === false || $wp_notif_key === false) {
        // Don't have to get the notif
        GcLogger::getLogger()->debug('don\'t have to get the notif');
        return false;
      }

      // Build the request
      $body = array(
          'public_key' => $gc_public_key,
          'platform' => 'wp',
          'private_key' => $wp_notif_key
      );

      if ($gc_notif_last_date !== false) {
        $body['last_date'] = strtotime($gc_notif_last_date);
      }

      // Make the request
      $url = constant('API_URL') . '/website/' . $gc_website_id . '/notif';
      $request = wp_remote_post($url, array('sslverify' => constant('SSLVERIFY'), 'body' => $body));

      // Extract the HTTP ret code and HTTP body
      $httpCode = wp_remote_retrieve_response_code($request);
      $body = wp_remote_retrieve_body($request);

      if ($httpCode !== 200) {
        GcLogger::getLogger()->error('GcMenu::get_notif() - Got HTTP ret !== 200 ( url: '.$url.')');

        update_option('gc-notif-error', json_encode(array('content' => __('Error Getting Notif', 'graphcomment-comment-system'))));
        return false;
      }

      if (!($ret = json_decode($body))) {
        GcLogger::getLogger()->error('GcMenu::get_notif() - Bad values received ( url: '.$url.')');

        // Bad JSON received, should never happened
        update_option('gc-notif-error', json_encode(array('content' => __('Error Getting Notif', 'graphcomment-comment-system'))));
        return false;
      }

      if (!isset($ret->comments)) {
        GcLogger::getLogger()->error('GcMenu::get_notif() - Params \'comments\' not sent ( url: '.$url.')');

        // One param is missing
        // Should never happened
        update_option('gc-notif-error', json_encode(array('content' => __('Error Getting Notif', 'graphcomment-comment-system'))));
        return false;
      }

      if ($ret->comments === 0) {
        delete_option('gc_notif_comments');
      }
      else {
        update_option('gc_notif_comments', $ret->comments);
      }

      return true;
    }
  }
}