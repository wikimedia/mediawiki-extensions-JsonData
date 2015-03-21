<?php
/**
 * JsonData is a generic JSON editing and templating interface for MediaWiki
 *
 * @file JsonData.php
 * @ingroup Extensions
 * @author Rob Lanphier
 * @copyright © 2011-2012 Rob Lanphier
 * @licence GNU General Public Licence 2.0 or later
 */

if ( !defined( 'MEDIAWIKI' ) ) die();

$wgExtensionCredits['Tasks'][] = array(
	'path'           => __FILE__,
	'name'           => 'JsonData',
	'author'         => 'Rob Lanphier',
	'descriptionmsg' => 'jsondata-desc',
	'url'            => 'https://www.mediawiki.org/wiki/Extension:JsonData',
);

$wgMessagesDirs['JsonData'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['JsonData'] = __DIR__ . '/JsonData.i18n.php';
$wgAutoloadClasses['JsonDataHooks'] = __DIR__ . '/JsonData.hooks.php';
$wgAutoloadClasses['JsonData'] = __DIR__ . '/JsonData_body.php';
$wgAutoloadClasses['JsonDataException'] = __DIR__ . '/JsonData_body.php';
$wgAutoloadClasses['JsonDataUnknownTagException'] = __DIR__ . '/JsonData_body.php';
$wgAutoloadClasses['JsonTreeRef'] = __DIR__ . '/JsonSchema.php';
$wgAutoloadClasses['TreeRef'] = __DIR__ . '/JsonSchema.php';
$wgAutoloadClasses['JsonSchemaException'] = __DIR__ . '/JsonSchema.php';
$wgAutoloadClasses['JsonUtil'] = __DIR__ . '/JsonSchema.php';
$wgAutoloadClasses['JsonSchemaIndex'] = __DIR__ . '/JsonSchema.php';
$wgAutoloadClasses['JsonDataMarkup'] = __DIR__ . '/JsonDataMarkup.php';

$wgHooks['BeforePageDisplay'][] = 'JsonDataHooks::beforePageDisplay';
$wgHooks['EditPage::showEditForm:fields'][] = 'JsonDataHooks::onEditPageShowEditFormInitial';
$wgHooks['EditPageBeforeEditToolbar'][] = 'JsonDataHooks::onEditPageBeforeEditToolbar';
$wgHooks['ParserFirstCallInit'][] = 'JsonDataHooks::onParserFirstCallInit';

$wgJsonDataNamespace = null;
$wgJsonDataSchemaFile = null;
$wgJsonData = null;

// On-wiki configuration article
$wgJsonDataConfigArticle = null;

$wgJsonDataConfigFile = null;

// Define these only for tags that don't have their own tag handlers, and thus
// need the default tag handler
$wgJsonDataDefaultTagHandlers = array( 'json', 'jsonschema' );

//
$wgJsonDataPredefinedData = array();
$wgJsonDataPredefinedData['openschema'] =  __DIR__ . "/schemas/openschema.json";
$wgJsonDataPredefinedData['schemaschema'] =  __DIR__ . "/schemas/schemaschema.json";
$wgJsonDataPredefinedData['configexample'] =  __DIR__ . "/example/configexample.json";
$wgJsonDataPredefinedData['configschema'] =  __DIR__ . "/schemas/jsondata-config-schema.json";
$wgJsonDataPredefinedData['simpleaddr'] =  __DIR__ . "/schemas/simpleaddr-schema.json";

$wgJsonDataConfig = array( 'srctype' => 'predefined', 'src' => 'configexample' );

$wgResourceModules['ext.jsonwidget'] = array(
	'scripts' => array(
		'json.js',
		'jsonedit.js',
		'mw.jsondata.js'
		),
	'styles' => array(
		'mw.jsondata.css',
		'jsonwidget.css'
		),
	'localBasePath' => __DIR__ . '/resources',
	'remoteExtPath' => 'JsonData/resources'
);


$wgHooks['GetPreferences'][] = 'JsonDataHooks::onGetPreferences';
$wgHooks['EditFilter'][] = 'JsonDataHooks::validateDataEditFilter';
