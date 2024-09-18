<?php

namespace MediaWiki\Extension\JsonData;

/**
 * Structure for representing a generic tree which each node is aware of its
 * context (can refer to its parent).  Used for schema refs.
 */
class TreeRef {
	/** @var array */
	public $node;
	/** @var self|null */
	public $parent;
	/** @var string|int|null */
	public $nodeindex;
	/** @var string|int */
	public $nodename;

	/**
	 * @param array $node
	 * @param self|null $parent
	 * @param string|int|null $nodeindex
	 * @param string|int $nodename
	 */
	public function __construct( $node, $parent, $nodeindex, $nodename ) {
		$this->node = $node;
		$this->parent = $parent;
		$this->nodeindex = $nodeindex;
		$this->nodename = $nodename;
	}
}
