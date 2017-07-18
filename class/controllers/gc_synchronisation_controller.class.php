<?php

require_once('gc_abstract_controller.class.php');

/**
 * @controller SynchronisationController
 *
 * Handle the synchronisation tab.
 * Set up a wp cron to synchronize GraphComment comments to WordPress database
 */

if (!class_exists('GcSynchronisationController')) {
  class GcSynchronisationController extends GcController {

    public function handleOptionForm() {
      GcLogger::getLogger()->debug('GcSynchronisationController::handleOptionForm()');

      if (!isset($this->post['gc_sync_comments'])) {
        GcLogger::getLogger()->error('GcSynchronisationController::handleOptionForm() - gc_sync_comments params not sent, redirect to \'settings\' page');

        // Param not sent, nothing to do
        return wp_redirect(admin_url('admin.php?page=settings'));
      }

      // The user had disable the synchronization, update the option
      if ($this->post['gc_sync_comments'] === 'false') {
        GcLogger::getLogger()->debug('GcSynchronisationController::handleOptionForm() - Action: Disable the sync');

        // Disable the synchronization
        update_option('gc_sync_comments', 'false');
        delete_option('gc_sync_last_success');
        delete_option('gc-sync-error');
        wp_clear_scheduled_hook('graphcomment_cron_task_sync_comments_action');
        // Print message
        update_option('gc-msg', json_encode(array('type' => 'warning', 'content' => __('Sync Deactivated', 'graphcomment-comment-system'), 'active_tab' => 'synchronization')));
        return wp_redirect(admin_url('admin.php?page=settings'));
      }

      if ($this->post['gc_sync_comments'] !== 'true') {
        GcLogger::getLogger()->debug('GcSynchronisationController::handleOptionForm() - params gc_sync_comments !== \'true\', redirect to settings page');

        return wp_redirect(admin_url('admin.php?page=settings'));
      }



      $gc_public_key = GcParamsService::getInstance()->graphcommentGetWebsite();
      if (is_null($gc_public_key)) {
        GcLogger::getLogger()->error('GcSynchronisationController::handleOptionForm() - No gc_public_key');


        /*
        ** The user hasn't set the public key yet
        */
        update_option('gc_sync_comments', 'false');
        delete_option('gc-sync-error');
        wp_clear_scheduled_hook('graphcomment_cron_task_sync_comments_action');
        update_option('gc-msg', json_encode(array('type' => 'danger', 'content' => 'No Public Key', 'active_tab' => 'synchronization')));
        return wp_redirect(admin_url('admin.php?page=settings'));
      }

      // The public key is set

      /*
       * User logged
       * Can request the API now
       * have to send the previous request's cookies
       */
      $request = wp_remote_post(constant('API_URL_SETUP_SYNC'), array(
          'sslverify' => constant('SSLVERIFY'),
          'headers' => array(
              'Authorization' => 'Bearer ' . GcParamsService::getInstance()->graphcommentGetClientToken()
          ),
          'body' => array(
              'public_key' => $gc_public_key, 'platform' => 'wp'
          )
      ));

      // Extract the HTTP ret code and HTTP body
      $httpCode = wp_remote_retrieve_response_code($request);
      $body = wp_remote_retrieve_body($request);

      $ret = false;

      // Token not authorize anymore
      if ($httpCode === 401) {
        GcLogger::getLogger()->error('GcSynchronisationController::handleOptionForm() - Got HTTP ret 401 -> renew token ( url: '.constant('API_URL_SETUP_SYNC').' )');

        // Relog the user
        return $this->graphcommentRenewToken();
      }
      // An error happened
      else if ($httpCode !== 200) {
        GcLogger::getLogger()->error('GcSynchronisationController::handleOptionForm() - Got HTTP ret !== 200 ( url: '.constant('API_URL_SETUP_SYNC').' )');

        update_option('gc-msg', json_encode(array('type' => 'danger', 'content' => __('Unknown Error', 'graphcomment-comment-system'), 'active_tab' => 'synchronisation')));
        return wp_redirect(admin_url('admin.php?page=settings'));
      }

      if (!($ret = json_decode($body))) {
        GcLogger::getLogger()->error('GcSynchronisationController::handleOptionForm() - Wrong values received ( url: '.constant('API_URL_SETUP_SYNC').' )');

        // Bad JSON received, should never happened
        update_option('gc_sync_comments', 'false');
        delete_option('gc-sync-error');
        wp_clear_scheduled_hook('graphcomment_cron_task_sync_comments_action');
        update_option('gc-msg', json_encode(array('type' => 'danger', 'content' => __('Unknown Error', 'graphcomment-comment-system'), 'active_tab' => 'synchronization')));
        return wp_redirect(admin_url('admin.php?page=settings'));
      }

      if (!isset($ret->website_id) || !isset($ret->wp_sync_key)) {
        GcLogger::getLogger()->error('GcSynchronisationController::handleOptionForm() - Missing params in received ret ( url: '.constant('API_URL_SETUP_SYNC').' )');

        // One param is missing
        // Should never happened
        update_option('gc_sync_comments', 'false');
        delete_option('gc-sync-error');
        wp_clear_scheduled_hook('graphcomment_cron_task_sync_comments_action');
        update_option('gc-msg', json_encode(array('type' => 'danger', 'content' => __('Unknown Error', 'graphcomment-comment-system'), 'active_tab' => 'synchronization')));
        return wp_redirect(admin_url('admin.php?page=settings'));
      }

      GcLogger::getLogger()->debug('GcSynchronisationController::handleOptionForm() - Everything is ok');

      // Everything is good, we can save the option
      update_option('gc_sync_comments', 'true');
      update_option('gc_website_id', $ret->website_id);
      update_option('gc_sync_key', $ret->wp_sync_key);
      delete_option('gc-sync-error');

      // Init CRON task to sync comments
      wp_schedule_single_event(time() + constant('SYNC_TIME_FIRST'), 'graphcomment_cron_task_sync_comments_action');
      update_option('gc-msg', json_encode(array('type' => 'success', 'content' => __('Sync Activated', 'graphcomment-comment-system'), 'active_tab' => 'synchronization')));

      return wp_redirect(admin_url('admin.php?page=settings'));
    }
  }
}