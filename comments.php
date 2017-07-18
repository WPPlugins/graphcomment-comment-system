<?php
/**
 * File that contains all the logic regarding the handling of comments
 */

$gc_public_key = GcParamsService::getInstance()->graphcommentGetWebsite();

if (empty($gc_public_key)) {
  $pluginData = get_plugin_data(dirname(__FILE__) . '/graphcomment.php');
  $html_error = "<div class=\"gc-wp-error\">\n";
  $html_error .= "<!-- " . $pluginData['Version'] . " -->\n";
  $html_error .= "  <div class=\"gc-wp-error-inner\">\n";
  $html_error .= "    <strong>" . __('Error', 'graphcomment-comment-system') . " :</strong>";
  $html_error .= __('GraphComment couldn\'t be load because your settings are invalid.', 'graphcomment-comment-system') . __('Please visit your admin panel and go to the GraphComment section and enter a valid website URL/ID.', 'graphcomment-comment-system');
  $html_error .= "  </div>\n";
  $html_error .= "</div>\n";

  echo $html_error;
  return;
}

?>

<div id="graphcomment"></div>
<!-- <?php $pluginData = get_plugin_data(dirname(__FILE__) . '/graphcomment.php'); echo $pluginData['Version']; ?> -->
<script type="text/javascript">
  /* * * CONFIGURATION VARIABLES: * * */
  window.gc_params = {
    page_title:       '<?php echo GcParamsService::getInstance()->graphcommentIdenfitierGetPostTitle(get_post()); ?>',
    identifier:       '<?php echo GcParamsService::getInstance()->graphcommentIdentifierGenerate(get_post()); ?>',
    graphcomment_id:  '<?php echo $gc_public_key; ?>'
  };

  /* * * DON'T EDIT BELOW THIS LINE * * */
  (function () {
    var gc = document.createElement('script');
    gc.type = 'text/javascript';
    gc.async = true;
    gc.src = '<?php echo constant('GRAPHCOMMENT_URL'); ?>/js/integration.js';
    (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(gc);
  })();
</script>
