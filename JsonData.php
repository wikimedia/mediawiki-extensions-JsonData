<?php
/**
 * JsonData is a generic JSON editing and templating interface for MediaWiki
 *
 * @file JsonData.php
 * @ingroup Extensions
 * @author Rob Lanphier
 * @copyright © 2011-2012 Rob Lanphier
 * @license GPL-2.0-or-later
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'JsonData' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['JsonData'] = __DIR__ . '/i18n';

	wfWarn(
		'Deprecated PHP entry point used for JsonData extension. Please use wfLoadExtension ' .
		'instead, see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return true;
} else {
	die( 'This version of the JsonData extension requires MediaWiki 1.31+' );
}
