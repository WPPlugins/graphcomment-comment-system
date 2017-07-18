<?php

  /*
   *  Settings page : Import
   *  Synchronizes comments from WordPress to GraphComment
   *  Controller : ../controllers/gc_import_controller.class.php
   */

  $gc_import_status = get_option('gc_import_status');
  $gc_import_error = get_option('gc_import_error_msg');
  $status = get_option('gc_import_status');
  $nbr_comment_import = get_option('gc_import_nbr_comment_import');
  $total = get_option('gc_import_total');

  function getPanelStatusClass($gc_status) {
    switch ($gc_status) {
      case 'error':
        return 'danger';
      case 'finished':
        return 'success';
      case 'pending':
        return 'warning';
      default:
        return 'info';
    }
  }
?>

<div class="row">
  <div class="col-xs-12">
    <div class="col-xs-6">

      <?php
      // Error during importation action
      if ($gc_import_error !== false) {
        $gc_import_error = json_decode($gc_import_error);
        echo '
                          <div class="row">
                            <div class="col-xs-12 alert alert-danger">
                              <span class="close" data-dismiss="alert" aria-label="close">&times;</span>
                                ' . $gc_import_error->content . '
                            </div>
                          </div>';
      }
      ?>

    </div>
    <div class="col-xs-6">

    </div>
  </div>

  <!-- From WordPress to GraphComment panel -->
  <div class="col-xs-6">
    <div id="graphcomment-import-pannel"
         class="panel panel-<?php echo getPanelStatusClass($gc_import_status); ?>">
      <div class="panel-heading"><?php _e('Import Pannel Heading', 'graphcomment-comment-system'); ?></div>

      <div class="panel-body">

        <blockquote>
          <p><?php _e('Importation Description', 'graphcomment-comment-system'); ?></p>
        </blockquote>

        <form class="col-xs-12" id="graphcomment-options-form-import" method="post" action="options.php">
          <input type="hidden" name="gc_action" value="importation"/>

          <?php
            if ($gc_import_error !== false) {
              echo '
                <div class="row">
                  <div class="col-xs-12 alert alert-danger">
                    <span class="close" data-dismiss="alert" aria-label="close">&times;</span>
                      ' . $gc_import_error . '
                  </div>
                </div>';
            }
          ?>

          <p>
            <input type="hidden" name="gc-import-status" value="<?php echo $status; ?>"/>

            <?php _e('import_status_label', 'graphcomment-comment-system') ?>:

            <?php if ($status === 'pending'): ?>
              <span class="gc-label-status-value label label-pill label-primary"><?php _e('Status Pending Label', 'graphcomment-comment-system') ?></span>

              <div class="progress">
                <div class="progress-bar progress-bar-info progress-bar-striped active" role="progressbar"
                     aria-valuenow="<?php echo $nbr_comment_import; ?>"
                     aria-valuemin="0" aria-valuemax="<?php echo $total; ?>"
                     style="width:<?php echo $nbr_comment_import / $total * 100; ?>%">
                  <?php echo floor($nbr_comment_import / $total * 100); ?>%
                </div>
              </div>

              ( <span class="gc-import-nbr-comment-import"><?php echo $nbr_comment_import; ?></span> comments imported on <?php echo $total; ?> )
              <br />
              <br />
              <dl class="dl-horizontal">
                <dt class="text-left"><?php _e('Import Started On Label', 'graphcomment-comment-system'); ?>:</dt>
                <dd><?php echo date("l j F Y G:i:s", strtotime(get_option('gc_import_date_begin'))); ?></dd>
                <dt class="gc-import-finished-date-hide text-left"><?php _e('Import Finished On Label', 'graphcomment-comment-system'); ?>:</dt>
                <dd class="gc-import-finished-date-hide gc-import-finished-date"></dd>
              </dl>

              <div class="gc-import-error-hide">
                <?php submit_button(__('Restart Import Button', 'graphcomment-comment-system')); ?>
              </div>

              <div class="gc-import-pending-stop">
                <?php submit_button(__('Cancel Import Button', 'graphcomment-comment-system')); ?>
              </div>

            <?php elseif ($status === 'error'): ?>
              <span class="label label-pill label-danger"><?php _e('Status Error Label', 'graphcomment-comment-system'); ?></span>

              <input type="hidden" name="gc-import-restart" value="true"/>

              <div class="progress">
                <div class="progress-bar progress-bar-success progress-bar-danger" role="progressbar"
                     aria-valuenow="<?php echo $nbr_comment_import; ?>"
                     aria-valuemin="0" aria-valuemax="<?php echo $total; ?>"
                     style="width:<?php echo $nbr_comment_import / $total * 100; ?>%">
                  <?php echo floor($nbr_comment_import / $total * 100); ?>%
                </div>
              </div>

              ( <span
                  class="gc-import-nbr-comment-import"><?php echo $nbr_comment_import; ?></span> comments imported on <?php echo $total; ?> )
              <br><br>
              <dl class="dl-horizontal">
                <dt>
                <p class="text-left"><?php _e('Import Started On Label', 'graphcomment-comment-system'); ?>:</p></dt>
                <dd><?php echo date("l j F Y G:i:s", strtotime(get_option('gc_import_date_begin'))); ?></dd>
              </dl>

              <?php submit_button(__('Restart Import Button', 'graphcomment-comment-system')); ?>

            <?php elseif ($status === 'finished'): ?>
              <span class="label label-pill label-success"><?php _e('Status Finished Label', 'graphcomment-comment-system') ?></span>

              <div class="progress">
                <div class="progress-bar progress-bar-success progress-bar-success" role="progressbar"
                     aria-valuenow="<?php echo $nbr_comment_import; ?>"
                     aria-valuemin="0" aria-valuemax="<?php echo $total; ?>"
                     style="width:<?php echo $nbr_comment_import / $total * 100; ?>%">
                  <?php echo floor($nbr_comment_import / $total * 100); ?>%
                </div>
              </div>

              <dl class="dl-horizontal">
                <dt>
                <p class="text-left"><?php _e('Import Started On Label', 'graphcomment-comment-system'); ?>:</p></dt>
                <dd><?php echo date("l j F Y G:i:s", strtotime(get_option('gc_import_date_begin'))); ?></dd>
                <dt>
                <p class="text-left"><?php _e('Import Finished On Label', 'graphcomment-comment-system'); ?>:</p></dt>
                <dd><?php echo date("l j F Y G:i:s", strtotime(get_option('gc_import_date_end'))); ?></dd>
              </dl>

            <?php else: ?>
              <span class="label label-pill label-default"><?php _e('Status No Import Label', 'graphcomment-comment-system') ?></span>

              <?php submit_button(__('Start Import Button', 'graphcomment-comment-system')); ?>

            <?php endif; ?>
          </p>
        </form>
      </div>
    </div>
  </div>
</div>