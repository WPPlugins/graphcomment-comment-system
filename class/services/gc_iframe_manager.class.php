<?php

if (!class_exists('GcIframeManager')) {
  class GcIframeManager
  {
    const GRAPHCOMMENT_ADMIN_IFRAME = 0;
    const GRAPHCOMMENT_CONNEXION_IFRAME = 1;
    const GRAPHCOMMENT_DISCONNEXION_IFRAME = 2;

    public static function getIframeUrl($iframe_name)
    {
      switch ($iframe_name) {
        case self::GRAPHCOMMENT_ADMIN_IFRAME :
          return constant('ADMIN_URL') . '/#/website/' . ((GcParamsService::getInstance()->graphcommentHasWebsites() && GcParamsService::getInstance()->graphcommentIsWebsiteChoosen()) ? GcParamsService::getInstance()->graphcommentGetWebsiteId() : 'new') . '?iframe=wp';
        case self::GRAPHCOMMENT_CONNEXION_IFRAME :
          return constant('API_URL') . '/oauth/authorize?response_type=code&client_id=' . GcParamsService::getInstance()->graphcommentGetClientKey() . '&redirect_uri=' . urlencode(GcParamsService::getInstance()->graphcommentGetRedirectUri()) . '&scope=*';
        case self::GRAPHCOMMENT_DISCONNEXION_IFRAME :
          return constant('API_URL') . '/oauth/disconnect';
        default :
          return constant('ADMIN_URL') . '/#/website/' . ((GcParamsService::getInstance()->graphcommentHasWebsites() && GcParamsService::getInstance()->graphcommentIsWebsiteChoosen()) ? GcParamsService::getInstance()->graphcommentGetWebsiteId() : 'new') . '?iframe=wp';
      }
    }
  }
}

