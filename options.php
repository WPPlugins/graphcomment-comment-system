<?php
/**
 * File containing all the logic for handling plugin options
 */

require_once('class/services/gc_date_tools.class.php');
require_once('class/controllers/gc_general_controller.class.php');
require_once('class/controllers/gc_import_controller.class.php');
require_once('class/controllers/gc_notif_controller.class.php');
require_once('class/controllers/gc_synchronisation_controller.class.php');
require_once('class/controllers/gc_select_website_controller.class.php');
require_once('class/services/gc_authorization.class.php');
require_once('class/services/gc_menu.class.php');
require_once('class/services/gc_api_service.class.php');
require_once('class/services/gc_import_service.class.php');
require_once('class/services/gc_thread_pairing.php');
require_once('class/templates/hello_login.template.php');
require_once('class/templates/settings_page_admin.template.php');

// Register the async action of comment import
GcImportController::registerAsyncAction();
// Create custom plugin settings menu and attach it to 'admin_menu' action and manage menu notification
$gc_menu = new GcMenu('admin_menu');
//Associate the function to this action
add_action('graphcomment_cron_task_sync_comments_action', '_graphcomment_cron_task_sync_comments_function');
// Associate the AJAX function that gonna update the advancement importation front page
add_action('wp_ajax_graphcomment_import_pending_get_advancement', '_graphcomment_import_pending_get_advancement');
// Associate the AJAX function that gonna delete the notification on the notifications page
add_action('wp_ajax_graphcomment_notif_delete', '_graphcomment_notif_delete');

/**
 *  Handle the form send by tht plugin option page
 */
function handle_option_form() {
  GcLogger::getLogger()->debug('options.php::handle_option_form() gc_action: '.$_POST['gc_action']);

  $gc_controller = null;

  // Register our settings
  if ($_POST['gc_action'] === 'select_website') {
    $gc_controller = new GcSelectWebsiteController($_POST);
  }
  else if ($_POST['gc_action'] === 'general') {
    if (isset($_POST['gc-change-website']) && $_POST['gc-change-website'] === 'true') {
      update_option('gc_change_website', 'true');
      return wp_redirect(admin_url('admin.php?page=settings'));
    }
    $gc_controller = new GcGeneralController($_POST);
  }
  else if ($_POST['gc_action'] === 'synchronization') {
    $gc_controller = new GcSynchronisationController($_POST);
  }
  else if ($_POST['gc_action'] === 'importation') {
    $gc_controller = new GcImportController($_POST);
  }
  else if ($_POST['gc_action'] === 'notification') {
    $gc_controller = new GcNotifController($_POST);
  }
  else if ($_POST['gc_action'] === 'gc_debug_change') {
    GcParamsService::getInstance()->graphcommentDebugChange();
    return wp_redirect(admin_url('admin.php?page=settings'));
  }

  if ($gc_controller !== null) {
    $gc_controller->handleOptionForm();
  } else {
    GcLogger::getLogger()->error('No controller found for the gc_option_tab');
    return wp_redirect(admin_url('admin.php?page=settings'));
  }
}

/**
 * _graphcomment_cron_task_sync_comments_function
 *
 * The CRON function is called 24 times a day
 * Sync the comment from GraphComment to WordPress
 */
function _graphcomment_cron_task_sync_comments_function() {

  // Then, reprogram the CRON
  wp_schedule_single_event(time() + constant('SYNC_TIME_OTHER'), 'graphcomment_cron_task_sync_comments_action');

  // Take these usefull options
  $gc_public_key=  GcParamsService::getInstance()->graphcommentGetWebsite();
  $gc_website_id = get_option('gc_website_id');
  $gc_sync_date_from = get_option('gc_sync_last_success');
  $gc_sync_key = get_option('gc_sync_key');

  $res = GcApiService::getNewComments($gc_website_id, $gc_sync_key, $gc_sync_date_from);
  if ($res['error'] !== false) {return;}

  // For each thread received by the API, save its comments
  foreach ($res['threads'] as $thread) {

    $post_id = GcThreadPairingService::getPostFromThread($thread, $gc_public_key);

    if ($post_id === 0) {
      // Should NEVER happen, but if it happen, go to the next thread
      continue;
    }

    // From here we have the good post object corresponding to the current $thread in the loop
    // We can use the $post_id to set it on every $comment in this LOOP

    $GcCommentPairingDao = new GcCommentPairingDao();

    // Check if we know some comments too, to check later for comments updated
    $ids = array_merge(
      array_map(function ($c) {
        return (isset($c->parent)) ? $c->parent : null;
      }, $thread->comments),
      array_map(function ($c) {
        return (isset($c->_id)) ? $c->_id : null;
      }, $thread->comments)
    );

    // Load the associated ids into $GcCommentPairingDao
    if ($GcCommentPairingDao->findIds($ids) === false) {
      // Error while getting the comments
      update_option('gc-sync-error', json_encode(array('content' => __('Error Intern MySql', 'graphcomment-comment-system'))));
      return;
    }

    /*
     * COMMENT LOOP
     */
    $i = 0;
    while (count($thread->comments) > 0) {

      $comment = new GcCommentBuilder($thread->comments[$i]);

      /*
       * Set the comment post
       */
      $comment->setPostId($post_id);

      /*
       * The comment is a first level comment
       * The parent_id has to be set to 0 in WordPress
       */
      if ($comment->isFirstLevel()) {
        $comment->setParent('0');
      }
      /*
       * This comment is an answer
       * We have to find its parents ID
       */
      else {
        $parent_id = $GcCommentPairingDao->findWordpressId($comment->getParentId());

        if ($parent_id === false) {
          // The ID of the parent was not found in the known paring table
          // We have to find the parent comment first to set it
          $i = 0;
          while ($i < count($thread->comments) && $thread->comments[$i]->_id != $comment->getParentId()) {
            $i++;
          }
          continue;
        } else {
          // Just set the good WordPress parent id
          $comment->setParent($parent_id);
        }
      }

      $comment_wp_id = $GcCommentPairingDao->findWordpressId($comment->getGraphCommentId());

      if ($comment_wp_id !== false) {
        // We already know this comment, we just have to update it
        $comment->setWordpressId($comment_wp_id);

        if ($comment->updateCommentInDatabase() === false) {
          // Error while getting the comments
          update_option('gc-sync-error', json_encode(array('content' => __('Error Intern MySql', 'graphcomment-comment-system'))));
          return;
        }

        array_splice($thread->comments, $i, 1);
        /*
         * Restart the iteration at the first_comment
         */
        $i = 0;
        continue;
      }


      // We don't know yet this comment, we have to insert it
      if ($comment->insertCommentInDatabase() === false) {
        // Error while getting the comments
        update_option('gc-sync-error', json_encode(array('content' => __('Error Intern MySql', 'graphcomment-comment-system'))));
        return;
      }

      if ($GcCommentPairingDao->insertKnowPairIds($comment->getWordpressId(), $comment->getGraphCommentId()) === false) {
        // Error while getting the comments
        update_option('gc-sync-error', json_encode(array('content' => __('Error Intern MySql', 'graphcomment-comment-system'))));
        return;
      }

      /*
       * Delete the current comment
       * We won't iterate on it anymore
       */
      array_splice($thread->comments, $i, 1);

      //Restart the iteration at the first_comment
      unset($comment);
      $i = 0;
    } // end loop comments
  } // end loop threads

  // Here we have to set the last_sync_date to NOW and delete the gc-sync-error because no error occurred
  update_option('gc_sync_last_success', date("Y-m-d H:i:s", time() - date("Z")));
  delete_option('gc-sync-error');
}

function _graphcomment_import_pending_get_advancement() {

  $gc_import_service = new GcImportService();
  echo json_encode($gc_import_service->getAjaxAdvancement());

  wp_die(); // this is required to terminate immediately and return a proper response
}

function _graphcomment_notif_delete() {

  GcLogger::getLogger()->debug('_graphcomment_notif_delete '.date("Y-m-d H:i:s", time() - date("Z")));

  update_option('gc_notif_last_date', date("Y-m-d H:i:s", time() - date("Z")));
  delete_option('gc_notif_comments');

  wp_die(); // this is required to terminate immediately and return a proper response
}


/**
 * Print the page that will be used to select the good website
 */
function _graphcomment_settings_page_select_website() {
  require('class/templates/settings_page_select_website.template.php');
}

function _graphcomment_settings_page_create_website() {
  require('class/templates/settings_page_create_website.template.php');
}

/**
 * Returns the plugin options page
 */
function _graphcomment_settings_page() {
  if (!GcParamsService::getInstance()->graphcommentHasWebsites()) {
    if (GcParamsService::getInstance()->graphcommentApiIsUp()) {
      return _graphcomment_settings_page_create_website();
    }
    // API is down
    else {
      update_option('gc-msg', json_encode(array('type' => 'danger', 'content' => __('API Down Msg', 'graphcomment-comment-system'), 'active_tab' => 'general')));
    }
  }
  else if ((GcParamsService::getInstance()->graphcommentHasWebsites() && !GcParamsService::getInstance()->graphcommentIsWebsiteChoosen()) ||
    get_option('gc_change_website') === 'true'
  ) {
    if (get_option('gc_change_website') !== 'true' && GcParamsService::getInstance()->graphcommentGetNbrWebsites() === 1) {
      GcParamsService::getInstance()->graphcommentSelectOnlyWebsite();
      // Don't return to continue the proccess
    }
    else {
      GcParamsService::getInstance()->graphcommentDeleteWebsite(true, true);
      return _graphcomment_settings_page_select_website();
    }
  }

  $activated = get_option('gc_activated'); // use in children template.
  $gc_sync_error = get_option('gc-sync-error');
  $gc_import_error = get_option('gc-import-error');

  $gc_msg = get_option('gc-msg');
  $gc_msg = ($gc_msg !== false) ? json_decode($gc_msg, true) : false;
  $active_tab = (!empty($gc_msg) && isset($gc_msg['active_tab'])) ? $gc_msg['active_tab'] : 'general';

  if (GcAuthorization::checkOrPrintOauthIframe()) {
    ?>

    <div class="graphcomment-options-container">
      <?php hello_login_template(); ?>

      <?php
        delete_option('gc-msg');
        if ($gc_msg !== false) {
          if (array_key_exists('content', $gc_msg) && $gc_msg['content']) {
            echo '
              <div class="row">
                <div class="col-lg-5 col-md-6 col-sm-7 col-xs-12 alert alert-' . $gc_msg['type'] . '">
                  <span class="close" data-dismiss="alert" aria-label="close">&times;</span>
                    ' . $gc_msg['content'] . '
                </div>
              </div>';
          }
        }
      ?>

      <ul class="nav nav-tabs">

        <li id="graphcomment-options-general-tab"
            class="graphcomment-options-tabs <?php echo ($active_tab === 'general' ? 'active' : ''); ?>"
            data-toggle="general">
          <a href="#"><?php _e('General Tab', 'graphcomment-comment-system'); ?></a>
        </li>


        <li id="graphcomment-options-synchronization-tab"
            class="graphcomment-options-tabs <?php echo($active_tab === 'synchronization' ? 'active' : ''); ?>"
            data-toggle="synchronization">
          <a href="#">
            <?php if ($gc_sync_error !== false) echo '<span class="glyphicon glyphicon-warning-sign graphcomment-tabs-alerts-sync" aria-hidden="true" style="color:#d54e21"></span> '; ?>
            <?php _e('Synchronization Tab', 'graphcomment-comment-system'); ?>
            <?php if ($gc_sync_error !== false) echo ' <span class="glyphicon glyphicon-warning-sign graphcomment-tabs-alerts-sync" aria-hidden="true" style="color:#d54e21"></span>'; ?>
          </a>
        </li>

        <li id="graphcomment-options-importation-tab"
            class="graphcomment-options-tabs <?php echo($active_tab === 'importation' ? 'active' : ''); ?>"
            data-toggle="importation">
          <a href="#">
            <?php if ($gc_import_error !== false) echo '<span class="glyphicon glyphicon-warning-sign graphcomment-tabs-alerts-sync" aria-hidden="true" style="color:#d54e21"></span> '; ?>
            <?php _e('Importation Tab', 'graphcomment-comment-system'); ?>
            <?php if ($gc_import_error !== false) echo ' <span class="glyphicon glyphicon-warning-sign graphcomment-tabs-alerts-sync" aria-hidden="true" style="color:#d54e21"></span>'; ?>
          </a>
        </li>

        <li id="graphcomment-options-notification-tab"
            class="graphcomment-options-tabs <?php echo($active_tab === 'notification' ? 'active' : ''); ?>"
            data-toggle="notification">
          <a href="#">
            <?php _e('Notification Tab', 'graphcomment-comment-system'); ?>
          </a>
        </li>

      </ul>

      <div class="tab-content">

        <!-- General Tab -->
        <div id="graphcomment-general" class="graphcomment-options-tab-content <?php echo($active_tab === 'general' ? 'active' : ''); ?>">
          <?php include 'class/templates/settings_page_general.template.php'; ?>
        </div>
        <!-- End General Tab -->

        <!-- Synchronization Tab -->
        <div id="graphcomment-synchronization"
             class="graphcomment-options-tab-content <?php echo($active_tab === 'synchronization' ? 'active' : ''); ?>">
          <?php include 'class/templates/settings_page_synchro.template.php'; ?>
        </div>
        <!-- End Synchronization Tab -->


        <!-- Importation Tab -->
        <div id="graphcomment-importation"
             class="graphcomment-options-tab-content <?php echo($active_tab === 'importation' ? 'active' : ''); ?>">
          <?php include 'class/templates/settings_page_import.template.php'; ?>
        </div>
        <!-- End Importation Tab -->


        <!-- Notification Tab -->
        <div id="graphcomment-notification"
             class="graphcomment-options-tab-content <?php echo($active_tab === 'notification' ? 'active' : ''); ?>">
          <?php include 'class/templates/settings_page_notif.template.php'; ?>
        </div>
        <!-- End Notification Tab -->
      </div>

      <div id="debug-mode" class="col-xs-12">
        <form id="debug-form" method="post" action="options.php">
          <?php _e('Debug Mode Label', 'graphcomment-comment-system'); ?>:
          <input type="hidden" name="gc_action" value="gc_debug_change"/>
          <span class="glyphicon glyphicon-<?php echo (GcParamsService::getInstance()->graphcommentDebugIsActivated() ? 'ok' : 'remove'); ?>" aria-hidden="true"></span>
          <button type="submit" role="button" class="gc_button_link"><?php echo (GcParamsService::getInstance()->graphcommentDebugIsActivated() ? _e('Deactivate Label', 'graphcomment-comment-system') : _e('Activate Label', 'graphcomment-comment-system')); ?></button>
        </form>
      </div>

    </div>

  <?php }
}

// Call the js scripts and css style
add_action('admin_init', '_graphcomment_load_requirement');
// Call register settings function
add_action('admin_init', '_graphcomment_register_settings');

/**
 * _graphcomment_load_requirement
 * Register and enqueue style and script into the options page
 */
function _graphcomment_load_requirement() {
  wp_register_script('bootstrap-js', plugins_url('/theme/vendors/bootstrap/dist/js/bootstrap.min.js', __FILE__), array('jquery', 'jquery-ui-core'));

  wp_enqueue_style('jquery-ui-style', plugins_url('/theme/vendors/jquery-ui/themes/smoothness/jquery-ui.min.css', __FILE__));

  // Include bootstrap just for our plugin option page
  if ($_SERVER['QUERY_STRING'] === 'page=graphcomment' || $_SERVER['QUERY_STRING'] === 'page=settings') {
    wp_enqueue_style('bootstrap-css', plugins_url('/theme/vendors/bootstrap/dist/css/bootstrap.min.css', __FILE__));
    wp_enqueue_style('bootstrap-css-theme', plugins_url('/theme/vendors/bootstrap/dist/css/bootstrap-theme.min.css', __FILE__), array('bootstrap-css'));

    wp_enqueue_style('gc-options-style', plugins_url('/theme/css/options.css', __FILE__));

    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-core');
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_script('bootstrap-js');
    wp_enqueue_script('gc-options-script', plugins_url('/theme/js/options.js', __FILE__), array('jquery-ui-datepicker', 'bootstrap-js'));
  }
}

/**
 * Registers the plugin options
 */
function _graphcomment_register_settings() {
  if (isset($_POST['gc_action'])) {
    handle_option_form();
  }
  // An OAuth2 redirection is in progress
  else if (isset($_GET['graphcomment_oauth_code']) && $_GET['graphcomment_oauth_code'] === 'true' && $_GET['code']) {
    GcParamsService::getInstance()->graphcommentCreateOauthToken($_GET['code']);

    require('class/templates/login_success.template.php');

    wp_die(); // this is required to terminate immediately and return a proper response
  }
}
