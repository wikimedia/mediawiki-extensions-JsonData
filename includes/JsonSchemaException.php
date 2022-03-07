<?php

class JsonSchemaException extends Exception {
	public $subtype;
	// subtypes: "validate-fail", "validate-fail-null"
}
