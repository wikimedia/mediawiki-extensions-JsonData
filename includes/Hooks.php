<?php

namespace MediaWiki\Extension\JsonData;

use Config;
use Exception;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\EditFilterHook;
use MediaWiki\Hook\EditPage__showEditForm_fieldsHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Title\Title;
use Parser;
use PPFrame;
use Skin;

/**
 * @phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
 */
class Hooks implements
	BeforePageDisplayHook,
	EditFilterHook,
	EditPage__showEditForm_fieldsHook,
	GetPreferencesHook,
	ParserFirstCallInitHook
{
	private Config $config;

	public function __construct(
		Config $config
	) {
		$this->config = $config;
	}

	/**
	 * BeforePageDisplay hook
	 * Adds the modules to the page
	 *
	 * @param OutputPage $out output page
	 * @param Skin $skin current skin
	 * @return void
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( $this->config->get( 'JsonData' ) !== null ) {
			$out->addModules( 'ext.jsonwidget' );
		}
	}

	/**
	 * Load the JsonData object if we're in one of the configured namespaces
	 * @param EditPage $editPage
	 * @param OutputPage $out
	 * @return bool
	 */
	public function onEditPage__showEditForm_fields( $editPage, $out ) {
		global $wgJsonData;
		$title = $editPage->getTitle();
		$ns = $title->getNamespace();

		if ( JsonData::isJsonDataNeeded( $ns ) ) {
			$wgJsonData = new JsonData( $title );
			try {
				$jsonref = $wgJsonData->getJsonRef();
				$jsonref->validate();
			} catch ( JsonSchemaException $e ) {
				// if the JSON is null, don't sweat an error, since that will
				// frequently be the case for new pages
				if ( $e->subtype != 'validate-fail-null' || !$editPage->firsttime ) {
					// TODO: clean up server error mechanism
					$wgJsonData->servererror .= "<b>" .
						wfMessage( 'jsondata-server-error' ) . "</b>: " .
						htmlspecialchars( $e->getMessage() ) . "<br/>";
				}
			} catch ( Exception $e ) {
				$wgJsonData->servererror .= "<b>" .
					wfMessage( 'jsondata-server-error' ) . "</b>: " .
					htmlspecialchars( $e->getMessage() ) . "<br/>";
			}
			$wgJsonData->outputEditor( $editPage );
		}
		return true;
	}

	/**
	 * Remove the edit toolbar from the form
	 * @param string &$toolbar
	 * @return bool
	 */
	public static function onEditPageBeforeEditToolbar( &$toolbar ) {
		$toolbar = '';
		return false;
	}

	/**
	 * Register the configured parser tags with default tag renderer.
	 * @param Parser $parser
	 * @return bool
	 */
	public function onParserFirstCallInit( $parser ) {
		foreach ( $this->config->get( 'JsonDataDefaultTagHandlers' ) as $tag ) {
			$parser->setHook( $tag, [ $this, 'jsonTagRender' ] );
		}
		return true;
	}

	/**
	 * Default parser tag renderer
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public function jsonTagRender( $input, array $args, Parser $parser, PPFrame $frame ) {
		global $wgJsonData;
		// @phan-suppress-next-line PhanUndeclaredProperty FIXME, not guaranteed to be PPFrame_Hash!
		$wgJsonData = new JsonData( $frame->title );

		$json = $input;
		$goodschema = true;
		try {
			$schematext = $wgJsonData->getSchemaText();
		} catch ( JsonDataException $e ) {
			$schematext = $wgJsonData->readJsonFromPredefined( 'openschema' );
			wfDebug( __METHOD__ . ": " . htmlspecialchars( $e->getMessage() ) . "\n" );
			$goodschema = false;
		}

		$schematitletext = $wgJsonData->getSchemaTitleText();
		if ( $goodschema && $schematitletext !== null ) {
			// Register dependency in templatelinks, using technique (and a
			// little code) from https://www.mediawiki.org/wiki/Manual:Tag_extensions
			$schematitle = Title::newFromText( $schematitletext );
			$schemaid = $schematitle ? $schematitle->getId() : 0;
			$parser->getOutput()->addTemplate(
				// @phan-suppress-next-line PhanTypeMismatchArgumentNullable FIXME, fails with null!
				$schematitle,
				$schemaid,
				$schematitle ? $schematitle->getLatestRevID() : 0
			);
		}

		$data = json_decode( $json, true );
		$schema = json_decode( $schematext, true );
		$rootjson = new JsonTreeRef( $data );
		$rootjson->attachSchema( $schema );
		$markup = JsonDataMarkup::getMarkup( $rootjson, 0 );
		return $parser->recursiveTagParse( $markup, $frame );
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences['jsondata-schemaedit'] = [
			'type' => 'toggle',
			'label-message' => 'jsondata-schemaedit-pref',
			'section' => 'misc/jsondata',
		];
		return true;
	}

	/** @inheritDoc */
	public function onEditFilter( $editor, $text, $section, &$error, $summary ) {
		// I can't remember if jsondataobj needs to be a singleton/global, but
		// will chance calling a new instance here.
		$title = $editor->getTitle();
		$ns = $title->getNamespace();
		if ( !JsonData::isJsonDataNeeded( $ns ) ) {
			return true;
		}
		$jsondataobj = new JsonData( $title );
		$json = JsonData::stripOuterTagsFromText( $text );
		try {
			$schematext = $jsondataobj->getSchemaText();
		} catch ( JsonDataException ) {
			$schematext = $jsondataobj->readJsonFromPredefined( 'openschema' );
			$error = "<b>" . wfMessage( 'jsondata-servervalidationerror' ) . "</b>: ";
			$error .= wfMessage( 'jsondata-invalidjson' );
		}
		$data = json_decode( $json, true );
		if ( $data === null ) {
			$error = "<b>" . wfMessage( 'jsondata-servervalidationerror' ) . "</b>: ";
			$error .= wfMessage( 'jsondata-invalidjson' );
			return true;
		}
		$schema = json_decode( $schematext, true );
		$rootjson = new JsonTreeRef( $data );
		$rootjson->attachSchema( $schema );
		try {
			$rootjson->validate();
		} catch ( JsonSchemaException $e ) {
			$error = "<b>" . wfMessage( 'jsondata-servervalidationerror' ) . "</b>: ";
			$error .= htmlspecialchars( $e->getMessage() );
		}
		return true;
	}
}
