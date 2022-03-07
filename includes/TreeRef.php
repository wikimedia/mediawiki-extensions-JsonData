<?php

namespace MediaWiki\Extension\JsonData;

/**
 * Structure for representing a generic tree which each node is aware of its
 * context (can refer to its parent).  Used for schema refs.
 */
class TreeRef {
	public $node;
	public $parent;
	public $nodeindex;
	public $nodename;

	public function __construct( $node, $parent, $nodeindex, $nodename ) {
		$this->node = $node;
		$this->parent = $parent;
		$this->nodeindex = $nodeindex;
		$this->nodename = $nodename;
	}
}
