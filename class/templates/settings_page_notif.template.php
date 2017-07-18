<?php

  /*
   *  Settings page : Notification
   *  Choose the notification option
   *  Controller : ../controllers/gc_notif_controller.class.php
   */

  $gc_notif_private_key = get_option('gc_notif_private_key');

?>


<form class="col-md-6 graphcomment-options-form" id="graphcomment-options-form-notif" method="post" action="options.php">

  <input type="hidden" name="gc_action" value="notification"/>

  <blockquote class="graphcomment-options-blockquote">
    <p><?php _e('Notification Description', 'graphcomment-comment-system'); ?></p>
  </blockquote>

  <p class="graphcomment-lite-info">
    <?php if ($gc_notif_private_key === false): ?>
      <?php _e('No Notif Configured Yet', 'graphcomment-comment-system'); ?>
    <?php else: ?>
      <?php _e('Notif Configured', 'graphcomment-comment-system'); ?>
    <?php endif; ?>
  </p>


  <div class="graphcomment-notification-button">
    <?php if ($gc_notif_private_key === false): ?>
      <input type="hidden" name="gc_notif_activation" value="true"/>
      <?php submit_button(__('Receive Notifications', 'graphcomment-comment-system')); ?>
    <?php else : ?>
      <?php submit_button(__('Dont Receive Notifications', 'graphcomment-comment-system')); ?>
    <?php endif; ?>
  </div>
</form>