<?php

namespace MediaWiki\Extension\JsonData;

/**
 * Structure for representing a generic tree which each node is aware of its
 * context (can refer to its parent).  Used for schema refs.
 */
class TreeRef {
	/** @var string|int|null */
	public $nodeindex;

	/**
	 * @param array $node
	 * @param self|null $parent
	 * @param string|int|null $nodeindex
	 * @param string|int $nodename
	 */
	public function __construct(
		public readonly array $node,
		public readonly ?self $parent,
		$nodeindex,
		public readonly string|int $nodename,
	) {
		$this->nodeindex = $nodeindex;
	}
}
