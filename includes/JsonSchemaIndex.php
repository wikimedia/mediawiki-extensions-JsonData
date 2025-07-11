<?php

namespace MediaWiki\Extension\JsonData;

use Exception;

/**
 * The JsonSchemaIndex object holds all schema refs with an "id", and is used
 * to resolve an idref to a schema ref.  This also holds the root of the schema
 * tree.  This also serves as sort of a class factory for schema refs.
 */
class JsonSchemaIndex {
	/** @var array|null */
	public $root;
	/** @var array[] */
	public $idtable;

	/**
	 * The whole tree is indexed on instantiation of this class.
	 *
	 * @param array|null $schema
	 */
	public function __construct( $schema ) {
		$this->root = $schema;
		$this->idtable = [];

		if ( $this->root === null ) {
			return;
		}

		$this->indexSubtree( $this->root );
	}

	/**
	 * Recursively find all of the ids in this schema, and store them in the
	 * index.
	 *
	 * @param array $schemanode
	 */
	public function indexSubtree( $schemanode ) {
		if ( !array_key_exists( 'type', $schemanode ) ) {
			$schemanode['type'] = 'any';
		}
		$nodetype = $schemanode['type'];
		switch ( $nodetype ) {
			case 'object':
				foreach ( $schemanode['properties'] as $key => $value ) {
					$this->indexSubtree( $value );
				}

				break;
			case 'array':
				foreach ( $schemanode['items'] as $value ) {
					$this->indexSubtree( $value );
				}

				break;
		}
		if ( isset( $schemanode['id'] ) ) {
			$this->idtable[$schemanode['id']] = $schemanode;
		}
	}

	/**
	 *  Generate a new schema ref, or return an existing one from the index if
	 *  the node is an idref.
	 *
	 * @param array $node
	 * @param TreeRef|null $parent
	 * @param string|int|null $nodeindex
	 * @param string|int $nodename
	 * @throws JsonSchemaException
	 * @return TreeRef
	 */
	public function newRef( $node, $parent, $nodeindex, $nodename ) {
		if ( array_key_exists( '$ref', $node ) ) {
			if ( strspn( $node['$ref'], '#' ) != 1 ) {
				$error = JsonUtil::uiMessage( 'jsonschema-badidref', $node['$ref'] );
				throw new JsonSchemaException( $error );
			}
			$idref = $node['$ref'];
			try {
				$node = $this->idtable[$idref];
			} catch ( Exception ) {
				$error = JsonUtil::uiMessage( 'jsonschema-badidref', $node['$ref'] );
				throw new JsonSchemaException( $error );
			}
		}

		return new TreeRef( $node, $parent, $nodeindex, $nodename );
	}
}
