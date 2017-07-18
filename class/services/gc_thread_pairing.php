<?php


class GcThreadPairingService
{

  private static function handlePostNotFoundError($identifier) {
    GcLogger::getLogger()->error('options.php::cron_task_sync_comments handlePostNotFoundError('.$identifier.')');

    // Error while getting the comments
    update_option('gc-sync-error', json_encode(array('content' => __('Error Getting Sync', 'graphcomment-comment-system'))));
  }

  private static function extractIdentifier($thread, $gc_public_key)
  {
    $identifier = null;
    $substring = $gc_public_key . '@';

    if (substr($thread->full_id, 0, strlen($substring)) == $substring) {
      $identifier = substr($thread->full_id, strlen($substring));
    }

    return $identifier;
  }

  private static function getPostFromSlug($slug) {
    $args = array(
        'name'        => $slug,
        'post_type' => 'post',
        'numberposts' => 1
    );
    $wp_posts = get_posts($args);

    if ($wp_posts) {
      return $wp_posts[0]->ID;
    }

    // Else in some cases it could be a page :
    $args = array(
        'name'        => $slug,
        'post_type' => 'page',
        'numberposts' => 1
    );
    $wp_posts = get_posts($args);

    if ($wp_posts) {
      return $wp_posts[0]->ID;
    }

    // else
    return 0;
  }

  public static function getPostFromThread($thread, $gc_public_key)
  {
    $identifier = self::extractIdentifier($thread, $gc_public_key);
    $post_id = 0;

    // Still an old thread, identifier begin with `http`
    if (strpos($identifier, 'http') === 0) {
      $post_id = url_to_postid($identifier);
    }

    // If not found, try with the new way
    if ($post_id === 0) {
      $post_id = self::getPostFromSlug($identifier);
    }

    if ($post_id === 0) {
      self::handlePostNotFoundError($identifier);
    }

    return $post_id;
  }
}
