<?php

namespace MediaWiki\Extension\JsonData;

use Exception;

class JsonSchemaException extends Exception {
	/** @var string */
	public $subtype;
	// subtypes: "validate-fail", "validate-fail-null"
}
