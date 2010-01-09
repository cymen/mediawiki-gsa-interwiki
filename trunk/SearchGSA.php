<?php
# Alert the user that this is not a valid entry point to MediaWiki if they try to access the skin file directly.
if (!defined('MEDIAWIKI')) {
   echo 'To install my extension, put the following line in LocalSettings.php:';
   echo 'require_once( "$IP/extensions/mediawiki-gsa-interwiki/SearchGSA.php" );';
   exit( 1 );
}

$dir = dirname(__FILE__) . '/';

$wgAutoloadClasses['SearchGSA'] = $dir . 'SearchGSA_body.php';        # Tell MediaWiki to load the extension body.
$wgExtensionMessagesFiles['SearchGSA'] = $dir . 'SearchGSA.i18n.php';


$wgExtensionCredits['other'][] = array(
   'name'           => 'mediawiki-gsa-interwiki',
   'author'         => 'Jeremy Orem, Cymen Vig',
   'version'        => '0.4',
   'description'    => 'Use Google Search Appliance (GSA) to as search engine and return results from other local wikis.',
   'descriptionmsg' => 'mediawiki-gsa-interwiki-desc',
   'url'            => 'http://code.google.com/p/mediawiki-gsa-interwiki/',
);


