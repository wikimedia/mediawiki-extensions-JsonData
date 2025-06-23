<?php

namespace MediaWiki\Extension\JsonData;

use Message;

class JsonUtil {
	/**
	 * Converts the string into something safe for an HTML id.
	 * performs the easiest transformation to safe id, but is lossy
	 *
	 * @param string|int $var
	 * @return string
	 */
	public static function stringToId( $var ) {
		if ( is_int( $var ) ) {
			return (string)$var;
		} elseif ( is_string( $var ) ) {
			return preg_replace( '/[^a-z0-9\-_:\.]/i', '', $var );
		} else {
			$msg = self::uiMessage( 'jsonschema-idconvert', print_r( $var, true ) );
			throw new JsonSchemaException( $msg );
		}
	}

	/**
	 * Given a type (e.g. 'object', 'integer', 'string'), return the default/empty
	 * value for that type.
	 *
	 * @param string $thistype
	 * @return mixed
	 */
	public static function getNewValueForType( $thistype ) {
		switch ( $thistype ) {
			case 'object':
				$newvalue = [];
				break;
			case 'array':
				$newvalue = [];
				break;
			case 'number':
			case 'integer':
				$newvalue = 0;
				break;
			case 'string':
				$newvalue = "";
				break;
			case 'boolean':
				$newvalue = false;
				break;
			default:
				$newvalue = null;
				break;
		}

		return $newvalue;
	}

	/**
	 * Return a JSON-schema type for arbitrary data $foo
	 *
	 * @param mixed|null $foo
	 * @return string|null
	 */
	public static function getType( $foo ) {
		if ( $foo === null ) {
			return null;
		}

		switch ( gettype( $foo ) ) {
			case "array":
				$retval = "array";
				foreach ( array_keys( $foo ) as $key ) {
					if ( !is_int( $key ) ) {
						$retval = "object";
					}
				}
				return $retval;
			case "integer":
			case "double":
				return "number";
			case "boolean":
				return "boolean";
			case "string":
				return "string";
			default:
				return null;
		}
	}

	/**
	 * Generate a schema from a data example ($parent)
	 *
	 * @param mixed $parent
	 * @return array
	 */
	public static function getSchemaArray( $parent ) {
		$schema = [];
		$schema['type'] = self::getType( $parent );
		switch ( $schema['type'] ) {
			case 'object':
				$schema['properties'] = [];
				foreach ( $parent as $name ) {
					$schema['properties'][$name] = self::getSchemaArray( $parent[$name] );
				}

				break;
			case 'array':
				$schema['items'] = [];
				$schema['items'][0] = self::getSchemaArray( $parent[0] );
				break;
		}

		return $schema;
	}

	/**
	 * User interface messages suitable for translation.
	 * Note: this merely acts as a passthrough to MediaWiki's wfMessage call.
	 * @param string $key
	 * @param mixed ...$params
	 * @return Message|string
	 */
	public static function uiMessage( $key, ...$params ) {
		if ( function_exists( 'wfMessage' ) ) {
			return wfMessage( $key, ...$params );
		} else {
			// TODO: replace this with a real solution that works without
			// MediaWiki
			return implode( ' ', [ $key, ...$params ] );
		}
	}
}
