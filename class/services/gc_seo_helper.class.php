<?php

class gcSeoHelper {

  /**
   * Check if Bot is visiting.
   * @return boolean true is a bot, false is not
   */
  public static function request_is_bot()
  {
    $seo_activated = get_option('gc_seo_activated');

    if (!$seo_activated) {
      return false;
    }

    $botlist = array("Googlebot", "Googlebot-Mobile", "Googlebot-Image",
        "Googlebot-News", "Googlebot-Video", "Mediapartners-Google",
        "bingbot", "slurp", "java", "wget", "curl", "Commons-HttpClient",
        "Python-urllib", "libwww", "httpunit", "nutch", "phpcrawl",
        "msnbot", "jyxobot", "FAST-WebCrawler", "FAST Enterprise Crawler",
        "biglotron", "teoma", "convera", "seekbot", "gigabot", "gigablast",
        "exabot", "ngbot", "ia_archiver", "GingerCrawler", "webmon ",
        "httrack", "webcrawler", "grub.org", "UsineNouvelleCrawler",
        "antibot", "netresearchserver", "speedy", "fluffy", "bibnum.bnf",
        "findlink", "msrbot", "panscient", "yacybot", "AISearchBot", "IOI",
        "ips-agent", "tagoobot", "MJ12bot", "dotbot", "woriobot", "yanga",
        "buzzbot", "mlbot", "yandexbot", "purebot", "Linguee Bot",
        "Voyager", "CyberPatrol", "voilabot", "baiduspider", "citeseerxbot",
        "spbot", "twengabot", "postrank", "turnitinbot", "scribdbot",
        "page2rss", "sitebot", "linkdex", "Adidxbot", "blekkobot", "ezooms",
        "dotbot", "Mail.RU_Bot", "discobot", "heritrix", "findthatfile",
        "europarchive.org", "NerdByNature.Bot", "sistrix crawler",
        "ahrefsbot", "Aboundex", "domaincrawler", "wbsearchbot", "summify",
        "ccbot", "edisterbot", "seznambot", "ec2linkfinder", "gslfbot",
        "aihitbot", "intelium_bot", "facebookexternalhit", "yeti",
        "RetrevoPageAnalyzer", "lb-spider", "sogou", "lssbot", "careerbot",
        "wotbox", "wocbot", "ichiro", "DuckDuckBot", "lssrocketcrawler",
        "drupact", "webcompanycrawler", "acoonbot", "openindexspider",
        "gnam gnam spider", "web-archive-net.com.bot", "backlinkcrawler",
        "coccoc", "integromedb", "content crawler spider", "toplistbot",
        "seokicks-robot", "it2media-domain-crawler", "ip-web-crawler.com",
        "siteexplorer.info", "elisabot", "proximic", "changedetection",
        "blexbot", "arabot", "WeSEE:Search", "niki-bot", "CrystalSemanticsBot",
        "rogerbot", "360Spider", "psbot", "InterfaxScanBot",
        "Lipperhey SEO Service", "CC Metadata Scaper", "g00g1e.net",
        "GrapeshotCrawler", "urlappendbot", "brainobot", "fr-crawler",
        "binlar", "SimpleCrawler", "Livelapbot", "Twitterbot", "cXensebot",
        "smtbot", "bnf.fr_bot", "A6-Indexer", "ADmantX", "Facebot",
        "Twitterbot", "OrangeBot", "memorybot", "AdvBot", "MegaIndex",
        "SemanticScholarBot", "ltx71", "nerdybot", "xovibot", "BUbiNG",
        "Qwantify", "archive.org_bot", "Applebot", "TweetmemeBot",
        "crawler4j", "findxbot", "SemrushBot", "yoozBot", "lipperhey",
        "y!j-asr", "Domain Re-Animator Bot", "AddThis", "Screaming Frog SEO Spider",
        "MetaURI", "Scrapy", "LivelapBot", "OpenHoseBot", "CapsuleChecker",
        "collection@infegy.com", "IstellaBot", "DeuSu", "betaBot",
        "Cliqzbot", "MojeekBot", "netEstate NE Crawler", "SafeSearch microdata crawler",
        "Gluten Free Crawler", "Sonic", "Sysomos", "Trove", "deadlinkchecker");

    foreach ($botlist as $bot) {
      if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], $bot) !== false)
        return true;  // Is a bot
    }

    return false; // Not a bot
  }

}