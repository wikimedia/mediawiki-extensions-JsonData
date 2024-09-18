<?php
/**
 * @ingroup Extensions
 * @author Rob Lanphier
 * @copyright © 2011-2012 Rob Lanphier
 * @license http://jsonwidget.org/LICENSE BSD-3-Clause
 */

/**
 * Internal terminology:
 *   Node: "node" in the graph theory sense, but specifically, a node in the
 *    raw PHP data representation of the structure
 *   Ref: a node in the object tree.  Refs contain nodes and metadata about the
 *    nodes, as well as pointers to parent refs
 */

namespace MediaWiki\Extension\JsonData;

/**
 * Structure for representing a data tree, where each node (ref) is aware of its
 * context and associated schema.
 */
class JsonTreeRef {
	/** @var array|null */
	public $node;
	/** @var self|null */
	private $parent;
	/** @var string|int|null */
	private $nodeindex;
	/** @var string|null */
	private $nodename;
	/** @var TreeRef|null */
	private $schemaref;
	/** @var string */
	private $fullindex;
	/** @var array */
	private $datapath;
	/** @var JsonSchemaIndex|null */
	private $schemaindex;

	/**
	 * @param array|null $node
	 * @param self|null $parent
	 * @param string|int|null $nodeindex
	 * @param string|null $nodename
	 * @param TreeRef|null $schemaref
	 */
	public function __construct( $node, $parent = null, $nodeindex = null, $nodename = null, $schemaref = null ) {
		$this->node = $node;
		$this->parent = $parent;
		$this->nodeindex = $nodeindex;
		$this->nodename = $nodename;
		$this->schemaref = $schemaref;
		$this->fullindex = $this->getFullIndex();
		$this->datapath = [];
		if ( $schemaref !== null ) {
			$this->attachSchema();
		}
	}

	/**
	 * Associate the relevant node of the JSON schema to this node in the JSON
	 *
	 * @param array|null $schema
	 */
	public function attachSchema( $schema = null ) {
		if ( $schema !== null ) {
			$this->schemaindex = new JsonSchemaIndex( $schema );
			$this->nodename = $schema['title'] ?? 'Root node';
			$this->schemaref = $this->schemaindex->newRef( $schema, null, null, $this->nodename );
		} elseif ( $this->parent !== null ) {
			$this->schemaindex = $this->parent->schemaindex;
		}
	}

	/**
	 *  Return the title for this ref, typically defined in the schema as the
	 *  user-friendly string for this node.
	 *
	 * @return string|int
	 */
	public function getTitle() {
		if ( isset( $this->nodename ) ) {
			return $this->nodename;
		} elseif ( isset( $this->node['title'] ) ) {
			return $this->node['title'];
		} else {
			return $this->nodeindex;
		}
	}

	/**
	 * Rename a user key.  Useful for interactive editing/modification, but not
	 * so helpful for static interpretation.
	 *
	 * @param string|int $newindex
	 */
	public function renamePropname( $newindex ) {
		$oldindex = $this->nodeindex;
		$this->parent->node[$newindex] = $this->node;
		$this->nodeindex = $newindex;
		$this->nodename = $newindex;
		$this->fullindex = $this->getFullIndex();
		unset( $this->parent->node[$oldindex] );
	}

	/**
	 * Return the type of this node as specified in the schema.  If "any",
	 * infer it from the data.
	 *
	 * @return string|null
	 */
	public function getType() {
		if ( array_key_exists( 'type', $this->schemaref->node ) ) {
			$nodetype = $this->schemaref->node['type'];
		} else {
			$nodetype = 'any';
		}

		if ( $nodetype == 'any' ) {
			if ( $this->node === null ) {
				return null;
			} else {
				return JsonUtil::getType( $this->node );
			}
		} else {
			return $nodetype;
		}
	}

	/**
	 * Return a unique identifier that may be used to find a node.  This
	 * is only as robust as stringToId is (i.e. not that robust), but is
	 * good enough for many cases.
	 *
	 * @return string
	 */
	public function getFullIndex() {
		if ( $this->parent === null ) {
			return "json_root";
		} else {
			return $this->parent->getFullIndex() . "." . JsonUtil::stringToId( $this->nodeindex );
		}
	}

	/**
	 *  Get a path to the element in the array.  if $foo['a'][1] would load the
	 *  node, then the return value of this would be array('a',1)
	 *
	 * @return array
	 */
	public function getDataPath() {
		if ( !is_object( $this->parent ) ) {
			return [];
		} else {
			$retval = $this->parent->getDataPath();
			$retval[] = $this->nodeindex;
			return $retval;
		}
	}

	/**
	 *  Return path in something that looks like an array path.  For example,
	 *  for this data: [{'0a':1,'0b':{'0ba':2,'0bb':3}},{'1a':4}]
	 *  the leaf node with a value of 4 would have a data path of '[1]["1a"]',
	 *  while the leaf node with a value of 2 would have a data path of
	 *  '[0]["0b"]["oba"]'
	 *
	 * @return string
	 */
	public function getDataPathAsString() {
		$retval = "";
		foreach ( $this->getDataPath() as $item ) {
			$retval .= '[' . json_encode( $item ) . ']';
		}
		return $retval;
	}

	/**
	 *  Return data path in user-friendly terms.  This will use the same
	 *  terminology as used in the user interface (1-indexed arrays)
	 *
	 * @return string
	 */
	public function getDataPathTitles() {
		if ( !is_object( $this->parent ) ) {
			return $this->getTitle();
		} else {
			return $this->parent->getDataPathTitles() . ' -> '
				. $this->getTitle();
		}
	}

	/**
	 * Return the child ref for $this ref associated with a given $key
	 *
	 * @param string $key
	 * @return self
	 */
	public function getMappingChildRef( $key ) {
		$snode = $this->schemaref->node;
		$nodename = null;
		if ( array_key_exists( 'properties', $snode ) &&
			array_key_exists( $key, $snode['properties'] ) ) {
			$schemadata = $snode['properties'][$key];
			$nodename = $schemadata['title'] ?? $key;
		} elseif ( array_key_exists( 'additionalProperties', $snode ) ) {
			// additionalProperties can *either* be false (a boolean) or can be
			// defined as a schema (an object)
			if ( $snode['additionalProperties'] == false ) {
				$msg = JsonUtil::uiMessage( 'jsonschema-invalidkey',
											$key, $this->getDataPathTitles() );
				throw new JsonSchemaException( $msg );
			} else {
				$schemadata = $snode['additionalProperties'];
				$nodename = $key;
			}
		} else {
			// return the default schema
			$schemadata = [];
			$nodename = $key;
		}
		$value = $this->node[$key];
		$schemai = $this->schemaindex->newRef( $schemadata, $this->schemaref, $key, $key );
		$jsoni = new JsonTreeRef( $value, $this, $key, $nodename, $schemai );
		return $jsoni;
	}

	/**
	 * Return the child ref for $this ref associated with a given index $i
	 *
	 * @param int $i
	 * @return self
	 */
	public function getSequenceChildRef( $i ) {
		// TODO: make this conform to draft-03 by also allowing single object
		if ( array_key_exists( 'items', $this->schemaref->node ) ) {
			$schemanode = $this->schemaref->node['items'][0];
		} else {
			$schemanode = [];
		}
		$itemname = $schemanode['title'] ?? 'Item';
		$nodename = $itemname . " #" . ( (string)$i + 1 );
		$schemai = $this->schemaindex->newRef( $schemanode, $this->schemaref, 0, $i );
		$jsoni = new JsonTreeRef( $this->node[$i], $this, $i, $nodename, $schemai );
		return $jsoni;
	}

	/**
	 * Validate the JSON node in this ref against the attached schema ref.
	 * Return true on success, and throw a JsonSchemaException on failure.
	 *
	 * @return true
	 */
	public function validate() {
		$datatype = JsonUtil::getType( $this->node );
		$schematype = $this->getType();
		if ( $datatype == 'array' && $schematype == 'object' ) {
			// PHP datatypes are kinda loose, so we'll fudge
			$datatype = 'object';
		}
		if ( $datatype == 'number' && $schematype == 'integer' &&
			 $this->node == (int)$this->node ) {
			// Alright, it'll work as an int
			$datatype = 'integer';
		}
		if ( $datatype != $schematype ) {
			if ( $datatype === null && !is_object( $this->parent ) ) {
				$msg = JsonUtil::uiMessage( 'jsonschema-invalidempty' );
				$e = new JsonSchemaException( $msg );
				$e->subtype = "validate-fail-null";
				throw( $e );
			} else {
				$datatype = $datatype === null ? "null" : $datatype;
				$msg = JsonUtil::uiMessage( 'jsonschema-invalidnode', $schematype, $datatype, $this->getDataPathTitles() );
				$e = new JsonSchemaException( $msg );
				$e->subtype = "validate-fail";
				throw( $e );
			}
		}
		switch ( $schematype ) {
			case 'object':
				$this->validateObjectChildren();
				break;
			case 'array':
				$this->validateArrayChildren();
				break;
		}
		return true;
	}

	/**
	 * @return true
	 */
	private function validateObjectChildren() {
		if ( array_key_exists( 'properties', $this->schemaref->node ) ) {
			foreach ( $this->schemaref->node['properties'] as $skey => $svalue ) {
				$keyRequired = array_key_exists( 'required', $svalue ) ? $svalue['required'] : false;
				if ( $keyRequired && !array_key_exists( $skey, $this->node ) ) {
					$msg = JsonUtil::uiMessage( 'jsonschema-invalid-missingfield' );
					$e = new JsonSchemaException( $msg );
					$e->subtype = "validate-fail-missingfield";
					throw $e;
				}
			}
		}

		foreach ( $this->node as $key => $value ) {
			$jsoni = $this->getMappingChildRef( $key );
			$jsoni->validate();
		}
		return true;
	}

	private function validateArrayChildren() {
		$max = count( $this->node );
		for ( $i = 0; $i < $max; $i++ ) {
			$jsoni = $this->getSequenceChildRef( $i );
			$jsoni->validate();
		}
	}
}
