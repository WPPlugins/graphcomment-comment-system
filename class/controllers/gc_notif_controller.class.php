<?php

require_once(__DIR__ . '/../services/gc_logger.class.php');
require_once('gc_abstract_controller.class.php');

/**
 * @controller NotifController
 *
 * Handle the notif tab.
 *
 */

if (!class_exists('GcNotifController')) {
  class GcNotifController extends GcController {

    private static function updateGcMsg($type, $content, $activeTab) {
      update_option('gc-msg',
          json_encode(array(
              'type' => $type,
              'content' => $content,
              'active_tab' => $activeTab
          ))
      );
    }

    public function handleOptionForm() {
      GcLogger::getLogger()->debug('GcNotifController::handleOptionForm()');

      function deactivateNotif() {
        GcLogger::getLogger()->debug('GcNotifController::handleOptionForm()::deactivateNotif()');

        delete_option('gc_notif_private_key');
        delete_option('gc_notif_comments');
        delete_option('gc_notif_last_date');
      }

      // The user deactivated the notifications
      if (empty($this->post['gc_notif_activation'])) {
        GcLogger::getLogger()->debug('GcNotifController::handleOptionForm() - Action: Deactivate Notifs');

        deactivateNotif();
        self::updateGcMsg('warning', __('Notification Deactivated', 'graphcomment-comment-system'), 'notification');
        return wp_redirect(admin_url('admin.php?page=settings'));
      }

      $gc_public_key = GcParamsService::getInstance()->graphcommentGetWebsite();

      // The user has no public key (should never happened)
      if (is_null($gc_public_key)) {
        GcLogger::getLogger()->error('GcGeneralController::handleOptionForm() - No gc_public_key');

        // Should never happened, because user gonna be redirected on the choose-site page before seeing the notif form
        deactivateNotif();
        return wp_redirect(admin_url('admin.php?page=settings'));
      }

      // The public key is set
      $res = GcApiService::setupNotif($gc_public_key);

      if ($res['error'] !== false) {
        if ($res['error'] === 401) {
          // Relog the user
          return $this->graphcommentRenewToken();
        }

        deactivateNotif();

        if (is_string($res['error'])) {
          update_option('gc-msg', json_encode(array(
              'type' => 'danger',
              'content' => __($res['error'], 'graphcomment-comment-system'),
              'active_tab' => 'notification'
          )));
          return wp_redirect(admin_url('admin.php?page=settings'));
        }
      }

      update_option('gc_website_id', $res['website_id']);
      update_option('gc_notif_private_key', $res['wp_notif_key']);
      update_option('gc_notif_last_date', date("Y-m-d H:i:s", time() - date("Z"))); // Set the notification date to today
      self::updateGcMsg('success', __('Notif Activated', 'graphcomment-comment-system'), 'notification');
      return wp_redirect(admin_url('admin.php?page=settings'));
    }
  }
}