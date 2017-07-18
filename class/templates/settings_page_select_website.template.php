<?php

  /*
   *  Settings page : Select Website
   *  Select the GraphComment website associated with this plugin
   *  Controller : ../controllers/gc_select_website_controller.class.php
   */

?>

<div class="row graphcomment-options-container">
  <div class="col-lg-12">

    <?php
    $gc_msg = get_option('gc-msg');
    delete_option('gc-msg');
    if ($gc_msg !== false) {
      $gc_msg = json_decode($gc_msg, true);
      if ($gc_msg['content']) {
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

    <?php if (get_option('gc_change_website') === 'true'): ?>
      <div class="row">
        <div class="col-lg-5 col-md-6 col-sm-7 col-xs-12 alert alert-warning">
          <?php _e('Change Website Message', 'graphcomment-comment-system'); ?>
        </div>
      </div>
    <?php else: ?>
      <h2>
        <?php _e('Choose Website Message', 'graphcomment-comment-system'); ?>
      </h2>
    <?php endif; ?>

    <?php
    delete_option('gc_change_website');
    ?>

    <form id="graphcomment-create-website-form" class="col-md-6 graphcomment-options-form" method="post" action="options.php">
      <input type="hidden" name="gc_action" value="select_website"/>

      <?php foreach(GcParamsService::getInstance()->graphcommentGetWebsites() as $website): ?>
        <div class="radio">
          <label>
            <input type="radio" name="gc_website_id" id="optionsRadios1" value="<?php echo $website->public_key; ?>">
            <strong><?php echo $website->public_key; ?></strong>, created on <?php $date = new DateTime($website->created_at); echo $date->format('Y-m-d') ?>
          </label>
        </div>
      <?php endforeach; ?>

      <label class="graphcomment-website-not-found"><?php _e('Website Not Found Label', 'graphcomment-comment-system'); ?>, <a id="graphcomment-create-website" ><?php _e('Create A New One', 'graphcomment-comment-system'); ?></a></label>

      <?php submit_button(__('Select Website Button Text', 'graphcomment-comment-system'), 'primary', 'gc_select_website_submit_button'); ?>

    </form>

  </div>
</div>

<?php
