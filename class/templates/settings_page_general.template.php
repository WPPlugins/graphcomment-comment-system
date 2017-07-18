<?php

  /*
   *  Settings page : General
   *  Set the general settings of the plugin
   *  Controller : ../controllers/gc_general_controller.class.php
   */

  /**
   * @var $activated string
   */

  $activated_all = get_option('gc_activated_all');
  $activated_from = get_option('gc_activated_from');
  $options_activated = array(
      'seo_activated' => get_option('gc_seo_activated')
  );
?>

<form class="row graphcomment-options-form" id="graphcomment-options-form-general" method="post" action="options.php">

  <input type="hidden" name="gc_action" value="general"/>

  <div class="col-md-12 graphcomment-fieldset">
    <div>
      <strong for="graphcomment_website_id_label"><?php _e('Website Id', 'graphcomment-comment-system'); ?>:</strong> <i><?php echo GcParamsService::getInstance()->graphcommentGetWebsite(); ?></i>
      <div class="gc_sub_action">
        <label><?php _e('Not Right Website Id', 'graphcomment-comment-system'); ?> </label><button type="submit" id="graphcomment-change-website" role="button" class="gc_button_link"> <?php _e('Change Website Button Label', 'graphcomment-comment-system'); ?></button>
      </div>
    </div>
  </div>

  <div class="col-md-12 graphcomment-fieldset">
    <h4 class="graphcomment-fieldset-title"><?php _e('Activation', 'graphcomment-comment-system') ?></h4>
    <div class="checkbox">
      <label>
        <input id="graphcomment_activated_checkbox" type="checkbox" name="gc_activated" value="true"
            <?php echo ($activated == true) ? 'checked' : ''; ?> /> <?php _e('Activate Graphcomment', 'graphcomment-comment-system'); ?>
      </label>
    </div>

    <div class="checkbox">
      <label>
        <input type="checkbox" name="gc_activated_all"
               value="true" <?php echo (!$activated) ? 'disabled' : ''; ?>
            <?php echo ($activated_all == true) ? 'checked' : ''; ?> /> <?php _e('Activate Graphcomment on all posts', 'graphcomment-comment-system'); ?>
      </label>
    </div>

    <div class="form-group">
      <label for="datepicker"><?php _e('Activate Graphcomment on posts from', 'graphcomment-comment-system'); ?></label>
      <input type="text" class="form-control" id="datepicker"
             name="gc_activated_from" <?php echo (!$activated || $activated_all) ? 'disabled' : ''; ?>
             value="<?php echo $activated_from ? $activated_from : date("Y-m-d", time() - date("Z")); ?>"/>
    </div>
  </div>

  <div class="col-md-12 graphcomment-fieldset">
    <h4 class="graphcomment-fieldset-title"><?php _e('Options', 'graphcomment-comment-system') ?></h4>
    <div class="checkbox">
      <label>
        <input id="graphcomment_seo_checkbox" type="checkbox" name="gc_seo_activated" value="true"
            <?php echo ($options_activated['seo_activated'] == true) ? 'checked' : ''; ?> /> <?php _e('Activate SEO (Replace by wordpress comment system when SE bot)', 'graphcomment-comment-system'); ?>
      </label>
    </div>
  </div>

  <div class="col-sm-12">
    <div class="pull-right">
      <?php submit_button(); ?>
    </div>
  </div>

</form>