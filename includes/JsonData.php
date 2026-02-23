<?php
/**
 * JsonData is a generic JSON editing and templating interface for MediaWiki
 *
 * @file JsonData_body.php
 * @ingroup Extensions
 * @author Rob Lanphier
 * @copyright © 2011-2012 Rob Lanphier
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\JsonData;

use MediaWiki\Content\TextContent;
use MediaWiki\EditPage\EditPage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;

class JsonData {
	/** @var OutputPage */
	private $out;
	/** @var Title */
	private $title;
	/** @var string */
	private $nsname;
	/** @var string|null */
	private $editortext;
	/** @var array|null */
	private $config;
	/** @var string|null */
	private $schematext;
	/** @var JsonTreeRef|null */
	private $jsonref;
	/** @var string */
	public $servererror;

	/** @var array|null */
	private $schemainfo;

	/**
	 * Function which decides if we even need to get instantiated
	 * @param int $ns
	 * @return bool
	 */
	public static function isJsonDataNeeded( $ns ) {
		global $wgJsonDataNamespace;
		return array_key_exists( $ns, $wgJsonDataNamespace );
	}

	/**
	 * @param Title $title
	 * @param OutputPage $out
	 */
	public function __construct( $title, OutputPage $out ) {
		global $wgJsonDataNamespace;
		$this->out = $out;
		$this->title = $title;
		$this->nsname = $wgJsonDataNamespace[$this->title->getNamespace()];
		$this->editortext = null;
		$this->config = null;
		$this->schematext = null;
		$this->jsonref = null;
		$this->servererror = '';
	}

	/**
	 * All of the PHP-generated HTML associated with JsonData goes here
	 * @param EditPage &$editPage
	 */
	public function outputEditor( &$editPage ) {
		$user = $editPage->getContext()->getUser();
		$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
		$servererror = $this->servererror;
		$config = $this->getConfig();
		$defaulttag = $config['namespaces'][$this->nsname]['defaulttag'];
		$this->out->addJsConfigVars( 'egJsonDataDefaultTag', $defaulttag );
		try {
			$schema = $this->getSchemaText();
		} catch ( JsonDataException $e ) {
			$schema = self::readJsonFromPredefined( 'openschema' );
			// TODO: clean up server error mechanism
			$servererror .= "<b>Server error</b>: " . htmlspecialchars( $e->getMessage() );
		}
		$this->out->addHTML( <<<HEREDOC
<div id="je_servererror">{$servererror}</div>
<div id="je_warningdiv"></div>

<div class="jsondata_tabs">
	<div class="vectorTabs">
		<ul>
HEREDOC
			);
		if ( $editPage->preview ) {
			$this->out->addHTML( <<<HEREDOC
			<li><span id="je_previewpane"><a>Preview</a></span></li>
HEREDOC
				);
		}
		if ( $this->out->getRequest()->getVal( 'wpDiff' ) ) {
			$this->out->addHTML( <<<HEREDOC
			<li><span id="je_diffpane"><a>Changes</a></span></li>
HEREDOC
				);
		}
		$this->out->addHTML( <<<HEREDOC
			<li><span id="je_formbutton"><a>Edit w/Form</a></span></li>
			<li><span id="je_sourcebutton"><a>Edit Source</a></span></li>
HEREDOC
			);

		if ( $user->isRegistered() && $userOptionsLookup->getOption( $user, 'jsondata-schemaedit' ) ) {
			$this->out->addHTML( <<<HEREDOC
			<li><span id="je_schemaexamplebutton"><a>Generate Schema</a></span></li>
HEREDOC
			);
		}
		$this->out->addHTML( <<<HEREDOC
		</ul>
	</div>
</div>

<div id="je_formdiv"></div>
<textarea id="je_schemaexamplearea" style="display: none" rows="30" cols="80">
</textarea>

<textarea id="je_schematextarea" style="display: none" rows="30" cols="80">
HEREDOC
			);
			$this->out->addHTML( htmlspecialchars( $schema ) );
			$this->out->addHTML( <<<HEREDOC
</textarea>
HEREDOC
			);
	}

	/**
	 * Read the config text from either $wgJsonDataConfigArticle or
	 * $wgJsonDataConfigFile
	 * @return array
	 */
	public function getConfig() {
		global $wgJsonDataConfigArticle, $wgJsonDataConfigFile;
		if ( $this->config === null ) {
			if ( $wgJsonDataConfigArticle !== null ) {
				$configText = self::readJsonFromArticle( $wgJsonDataConfigArticle );
				$this->config = json_decode( $configText, true );
			} elseif ( $wgJsonDataConfigFile !== null ) {
				$configText = file_get_contents( $wgJsonDataConfigFile );
				$this->config = json_decode( $configText, true );
			} else {
				$this->config = $this->getDefaultConfig();
			}
		}
		return $this->config;
	}

	/**
	 * @return array
	 */
	public function getDefaultConfig() {
		// TODO - better default config mechanism
		$configText = self::readJsonFromPredefined( 'configexample' );
		return json_decode( $configText, true );
	}

	/**
	 * Load appropriate editor text into the object (if it hasn't been yet),
	 * and return it.  This will either be the contents of the title being
	 * viewed, or it will be the newly-edited text being previewed.
	 *
	 * @return string|null
	 */
	public function getEditorText() {
		if ( $this->editortext === null ) {
			// on preview, pull $editortext out from the submitted text, so
			// that the author can change schemas during preview
			$this->editortext = $this->out->getRequest()->getText( 'wpTextbox1' );
			// wpTextbox1 is empty in normal editing, so pull it from article->getText() instead
			if ( $this->editortext === '' ) {
				$rev = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $this->title );
				$content = $rev?->getContent( SlotRecord::MAIN );
				$this->editortext = $content instanceof TextContent ? $content->getText() : '';
			}
		}
		return $this->editortext;
	}

	/**
	 * Get the schema attribute from the editor text.
	 *
	 * @return string|null
	 */
	private function getSchemaAttr() {
		$config = $this->getConfig();
		$editortext = $this->getEditorText();
		$tag = $config['namespaces'][$this->nsname]['defaulttag'];

		$schemaconfig = $config['tags'][$tag]['schema'];
		$schemaAttr = null;
		if ( isset( $schemaconfig['schemaattr'] ) && ( preg_match( '/^(\w+)$/', $schemaconfig['schemaattr'] ) > 0 ) ) {
			if ( preg_match( '/^<[\w]+\s+([^>]+)>/m', $editortext, $matches ) > 0 ) {
				/*
				 * Quick and dirty regex for parsing schema attributes that hits the 99% case.
				 * Bad matches: foo="bar' , foo='bar"
				 * Bad misses: foo='"r' , foo="'r"
				 * Works correctly in most common cases, though.
				 * \x27 is single quote
				 */
				$regex = '/\b' . $schemaconfig['schemaattr'] . '\s*=\s*["\x27]([^"\x27]+)["\x27]/';
				if ( preg_match( $regex, $matches[1], $subm ) > 0 ) {
					$schemaAttr = $subm[1];
				}
			}
		}
		return $schemaAttr;
	}

	/**
	 * Get the tag from the editor text.  Horrible kludge: this should probably
	 * be done with the MediaWiki parser somehow, but for now, just using a
	 * nasty regexp.
	 *
	 * @throws JsonDataException
	 * @return string|null
	 */
	private function getTagName() {
		// $config = $this->getConfig();
		$editortext = $this->getEditorText();
		$begintag = null;
		$endtag = null;
		if ( preg_match( '/^<([\w]+)[^>]*>/m', $editortext, $matches ) > 0 ) {
			$begintag = $matches[1];
			wfDebug( __METHOD__ . ': begin tag name: ' . $begintag . "\n" );
		}
		if ( preg_match( '/<\/([\w]+)>$/m', $editortext, $matches ) > 0 ) {
			$endtag = $matches[1];
			wfDebug( __METHOD__ . ': end tag name: ' . $endtag . "\n" );
		}
		if ( $begintag != $endtag ) {
			throw new JsonDataException( "Mismatched tags: {$begintag} and {$endtag}" );
		}
		return $begintag;
	}

	/**
	 * Return the schema title text.
	 * @return string|null
	 */
	public function getSchemaTitleText() {
		if ( $this->schemainfo === null ) {
			// getSchemaText populates schemainfo as an artifact
			$this->getSchemaText();
		}

		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable Initialized above
		if ( $this->schemainfo['srctype'] == 'article' ) {
			// @phan-suppress-next-line PhanTypeArraySuspiciousNullable Initialized above
			return $this->schemainfo['src'];
		} else {
			return null;
		}
	}

	/**
	 * Find the correct schema and output that schema in the right spot of
	 * the form.  The schema may come from one of several places:
	 * a.  If the "schemaattr" is defined for a namespace, then from the
	 *     associated attribute of the json/whatever tag.
	 * b.  A configured article
	 * c.  A configured file in wgJsonDataPredefinedData
	 * @throws JsonDataException
	 * @return string
	 */
	public function getSchemaText() {
		if ( $this->schematext === null ) {
			$this->schemainfo = [];
			$schemaTitleText = $this->getSchemaAttr();
			$config = $this->getConfig();
			$tag = $this->getTagName();
			if ( $tag === null ) {
				$tag = $config['namespaces'][$this->nsname]['defaulttag'];
			}
			if ( $schemaTitleText !== null ) {
				$this->schemainfo['srctype'] = 'article';
				$this->schemainfo['src'] = $schemaTitleText;
				$this->schematext = self::readJsonFromArticle( $schemaTitleText );
				if ( $this->schematext == '' ) {
					throw new JsonDataException( "Invalid schema definition in {$schemaTitleText}" );
				}
			} elseif ( $config['tags'][$tag]['schema']['srctype'] == 'article' ) {
				$this->schemainfo = $config['tags'][$tag]['schema'];
				$schemaTitleText = $this->schemainfo['src'];
				$this->schematext = self::readJsonFromArticle( $schemaTitleText );
				if ( $this->schematext == '' ) {
					throw new JsonDataException( 'Invalid schema definition in ' .
						$schemaTitleText . '.  Check your site configuation for this tag.' );
				}
			} elseif ( $config['tags'][$tag]['schema']['srctype'] == 'predefined' ) {
				$this->schemainfo = $config['tags'][$tag]['schema'];
				$schemaTitleText = $config['tags'][$tag]['schema']['src'];
				$this->schematext = self::readJsonFromPredefined( $schemaTitleText );
			} elseif ( empty( $config['tags'][$tag] ) ) {
				throw new JsonDataUnknownTagException( "Tag \"{$tag}\" not defined in JsonData site config" );
			} else {
				throw new JsonDataException( "Unknown error with JsonData site config" );
			}
			if ( strlen( $this->schematext ) == 0 ) {
				throw new JsonDataException( "Zero-length schema: " . $schemaTitleText );
			}
		}
		return $this->schematext;
	}

	/**
	 *  Parse the article/editor text as well as the corresponding schema text,
	 *  and load the result into an object (JsonTreeRef) that associates
	 *  each JSON node with its corresponding schema node.
	 *
	 * @return JsonTreeRef
	 */
	public function getJsonRef() {
		if ( $this->jsonref === null ) {
			$json = self::stripOuterTagsFromText( $this->getEditorText() );
			$schematext = $this->getSchemaText();
			$data = json_decode( $json, true );
			$schema = json_decode( $schematext, true );
			$this->jsonref = new JsonTreeRef( $data );
			$this->jsonref->attachSchema( $schema );
		}
		return $this->jsonref;
	}

	/**
	 * Read json-formatted data from an article, stripping off parser tags
	 * surrounding it.
	 *
	 * @param string $titleText
	 * @return string
	 */
	public static function readJsonFromArticle( $titleText ) {
		$title = Title::newFromText( $titleText );

		$rev = MediaWikiServices::getInstance()->getRevisionLookup()->getRevisionByTitle( $title );
		$content = $rev?->getContent( SlotRecord::MAIN );
		if ( $content instanceof TextContent ) {
			return preg_replace( '{^<\w++[^>]*>|</\w+>$}m', '', $content->getText() );
		}
		return '';
	}

	/**
	 * Strip the outer parser tags from some text
	 *
	 * @param string $text
	 * @return string
	 */
	public static function stripOuterTagsFromText( $text ) {
		return preg_replace( [ '/^<[\w]+[^>]*>/m', '/<\/[\w]+>$/m' ], [ "", "" ], $text );
	}

	/**
	 * Read json-formatted data from a predefined data file.
	 *
	 * @param string $filekey
	 * @return string
	 */
	public static function readJsonFromPredefined( $filekey ) {
		global $wgJsonDataPredefinedData;

		$file = $wgJsonDataPredefinedData[$filekey];

		if ( !file_exists( $file ) ) {
			$file = dirname( __DIR__ ) . "/$file";
		}

		return file_get_contents( $file );
	}
}
