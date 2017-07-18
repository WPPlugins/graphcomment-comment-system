<?php

  /*
   *  Settings page : GraphComment Backoffice
   *  Show the GraphComment backoffice in an iframe
   */

require_once(__DIR__.'/../services/gc_params_service.class.php');
require_once(__DIR__.'/../services/gc_iframe_manager.class.php');

/**
* Returns the plugin GraphComment Admin page
*/
function _graphcomment_settings_page_admin() {

  if (!GcParamsService::getInstance()->graphcommentOAuthIsLogged()) {
    GcParamsService::getInstance()->graphcommentOAuthInitConnection();
    $isDisconnect = get_option('graphcomment-disconnect');
  ?>

    <div class="graphcomment-options-container">
      <?php if ($isDisconnect === 'true'): ?>
        <?php delete_option('graphcomment-disconnect'); ?>
        <script>
          var gc_logout = true;
        </script>
        <iframe class="gc-connexion-iframe gc-iframe"
                src="<?php echo GcIframeManager::getIframeUrl(GcIframeManager::GRAPHCOMMENT_DISCONNEXION_IFRAME); ?>">
        </iframe>
      <?php else: ?>
        <iframe class="gc-connexion-iframe gc-iframe"
                src="<?php echo GcIframeManager::getIframeUrl(GcIframeManager::GRAPHCOMMENT_CONNEXION_IFRAME); ?>">
        </iframe>
      <?php endif; ?>
    </div>

  <?php } else { ?>

    <?php if (GcParamsService::getInstance()->graphcommentApiIsDown()): ?>
      <div class="row">
        <div class="col-lg-5 col-md-6 col-sm-7 col-xs-12 alert alert-danger">
            <?php _e('API Down Msg', 'graphcomment-comment-system'); ?>
        </div>
      </div>
    <?php else: ?>

      <?php
        $gc_notif_comments = get_option('gc_notif_comments');
      ?>
      <?php if ($gc_notif_comments !== false): ?>
        <div class="row">
          <div class="col-lg-5 col-md-6 col-sm-7 col-xs-12 alert alert-success">
            <span id="graphcomment-notif-delete" class="close" data-dismiss="alert" aria-label="close" data-toggle="tooltip" data-placement="top" title="<?php _e('Close Notification Text', 'graphcomment-comment-system'); ?>">&times;</span>
              <span id="graphcomment-notif-go-admin" data-toggle="tooltip" data-placement="top" title="<?php _e('Go To Graphcomment Admin Website', 'graphcomment-comment-system'); ?>">
                <?php _e('you_have', 'graphcomment-comment-system'); ?> <strong><u><?php echo $gc_notif_comments; ?></u></strong> <?php _e('new_comments', 'graphcomment-comment-system'); ?><span class="glyphicon glyphicon-bell graphcomment-tabs-alerts-notif graphcomment-notif-alert" aria-hidden="true"></span>
              </span>
          </div>
        </div>
      <?php endif; ?>

      <div class="graphcomment-admin-iframe-container">
        <script>
          var gc_token = '<?php echo GcParamsService::getInstance()->graphcommentGetClientToken(); ?>';
        </script>
        <iframe id="gc-iframe"
                class="graphcomment-admin-iframe gc-iframe"
                src="<?php echo GcIframeManager::getIframeUrl(GcIframeManager::GRAPHCOMMENT_ADMIN_IFRAME); ?>">
        </iframe>
        <?php hello_login_template(); ?>
      </div>

    <?php endif; ?>

  <?php }
}