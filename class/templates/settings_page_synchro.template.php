<?php

  /*
   *  Settings page : Synchronization
   *  Synchronizes the GraphComment comments back to WordPress
   *  Controller : ../controllers/gc_synchronisation_controller.class.php
   */

  /**
   * @var $activated string
   */

  $gc_sync_comments = get_option('gc_sync_comments');
  $gc_sync_date_from = get_option('gc_sync_last_success');

  function getSynchronizationDateFormatted($date_from_str) {

    if ($date_from_str === false) {
      return __('No Sync Yet', 'graphcomment-comment-system');
    }

    try {
      $wp_timezone = GcDateTools::wp_get_timezone_string();
      // get datetime object from site timezone
      $datetime = new DateTime($date_from_str, new DateTimeZone('UTC'));
      $datetime->setTimezone(new DateTimeZone($wp_timezone));

      $date_from_str = $datetime->format('Y-m-d H:i:s');
    } catch ( Exception $e ) {
      // you'll get an exception most commonly when the date/time string passed isn't a valid date/time
    }

    return __('Last Sync', 'graphcomment-comment-system') . $date_from_str . ' ('. $wp_timezone .')';
  }
?>

<div class="row">

  <div class="col-xs-12">
    <div class="col-xs-6">

      <?php
      // Error during synchronization action
      if ($gc_sync_error !== false) {
        $gc_sync_error = json_decode($gc_sync_error);
        echo '
            <div class="row">
              <div class="col-xs-12 alert alert-danger">
                <span class="close" data-dismiss="alert" aria-label="close">&times;</span>
                  ' . $gc_sync_error->content . '
              </div>
            </div>';
      }
      ?>

    </div>
    <div class="col-xs-6">

    </div>
  </div>

  <!-- From GraphComment to WordPress panel -->
  <div class="col-xs-6">

    <div class="panel panel-<?php echo ($gc_sync_error !== false ? 'danger' : ($gc_sync_comments === 'true' ? 'success' : 'info')); ?>">
      <div class="panel-heading"><?php _e('Synchro Pannel Heading', 'graphcomment-comment-system'); ?> <?php echo ' ( ' . ($gc_sync_comments === 'true' ? __('Activated Label', 'graphcomment-comment-system') : __('Not Activated Label', 'graphcomment-comment-system')) . ' )'; ?></div>
      <div class="panel-body">

        <blockquote>
          <p><?php _e('Synchronization Description', 'graphcomment-comment-system'); ?></p>
        </blockquote>

        <form class="col-xs-12" method="post" action="options.php">

          <input type="hidden" name="gc_action" value="synchronization"/>

          <p class="graphcomment-lite-info">
            <?php echo getSynchronizationDateFormatted($gc_sync_date_from); ?>
          </p>

          <div class="checkbox">
            <label>
              <input type="hidden" name="gc_sync_comments" value="false"/>
              <input id="graphcomment_sync_comments_checkbox" type="checkbox" name="gc_sync_comments"
                     value="true" <?php echo (!$activated) ? 'disabled' : ''; ?>
                  <?php echo($gc_sync_comments === 'true' ? 'checked' : ''); ?> /> <?php _e('sync_comments', 'graphcomment-comment-system'); ?>
            </label>
          </div>

          <?php submit_button(); ?>

        </form>

      </div>
    </div>
  </div>
</div>