<?php

namespace MediaWiki\Extension\JsonData;

use Exception;

class JsonSchemaException extends Exception {
	public $subtype;
	// subtypes: "validate-fail", "validate-fail-null"
}
