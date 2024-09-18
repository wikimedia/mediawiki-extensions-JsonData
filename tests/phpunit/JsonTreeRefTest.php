<?php

namespace MediaWiki\Extension\JsonData\Test;

use MediaWiki\Extension\JsonData\JsonData;
use MediaWiki\Extension\JsonData\JsonSchemaException;
use MediaWiki\Extension\JsonData\JsonSchemaIndex;
use MediaWiki\Extension\JsonData\JsonTreeRef;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\JsonData\JsonTreeRef
 */
class JsonTreeRefTest extends TestCase {

	public static function loadJsonRef( $jsonfile, $schemafile ) {
		$dir = dirname( __DIR__, 2 );
		$json = file_get_contents( "$dir/$jsonfile" );
		$schematext = file_get_contents( "$dir/$schemafile" );
		$data = json_decode( $json, true );
		$schema = json_decode( $schematext, true );
		$jsonref = new JsonTreeRef( $data );
		$jsonref->attachSchema( $schema );
		return $jsonref;
	}

	public function getSimpleTestData() {
		$testdata = [];
		$json = '{"a":1,"b":2,"c":3,"d":4,"e":5}';
		$schematext = '{"title": "Unrestricted JSON", "type": "any"}';
		$testdata['data'] = json_decode( $json, true );
		$testdata['schema'] = json_decode( $schematext, true );
		return [ $testdata ];
	}

	/**
	 * @dataProvider getSimpleTestData
	 * @covers \MediaWiki\Extension\JsonData\JsonSchemaIndex
	 */
	public function testJsonSimpleTestValidate( $data, $schema ) {
		$schemaIndex = new JsonSchemaIndex( $schema );
		$this->assertEquals( 'any', $schemaIndex->root['type'] );
		$nodename = $schema['title'] ?? 'Root node';
		$rootschema = $schemaIndex->newRef( $schema, null, null, $nodename );
		$rootjson = new JsonTreeRef( $data, null, null, $nodename, $rootschema );
		$this->assertEquals( 'Unrestricted JSON', $rootjson->getTitle() );
	}

	/**
	 * @dataProvider getSimpleTestData
	 */
	public function testJsonUtilGetTitleFromNode( $data, $schema ) {
		$nodename = $schema['title'] ?? 'Root node';
		$this->assertEquals( "Unrestricted JSON", $nodename );
	}

	/**
	 * @dataProvider getSimpleTestData
	 */
	public function testJsonSimpleTestSchemaValidate( $data, $schema ) {
		$schemaschematext = file_get_contents( dirname( __DIR__, 2 ) . '/schemas/schemaschema.json' );
		$schemaschema = json_decode( $schemaschematext, true );
		$jsonref = new JsonTreeRef( $schema );
		$jsonref->attachSchema( $schemaschema );
		$this->assertTrue( $jsonref->validate() );
	}

	public function getAddressTestData() {
		$testdata = [];
		$dir = dirname( __DIR__, 2 );
		$json = file_get_contents( $dir . '/example/addressexample.json' );
		$schematext = file_get_contents( $dir . '/schemas/addressbookschema.json' );
		$testdata['data'] = json_decode( $json, true );
		$testdata['schema'] = json_decode( $schematext, true );
		return [ $testdata ];
	}

	/**
	 * @dataProvider getAddressTestData
	 * @covers \MediaWiki\Extension\JsonData\JsonSchemaIndex
	 */
	public function testJsonAddressTestValidate( $data, $schema ) {
		$schemaIndex = new JsonSchemaIndex( $schema );
		$this->assertEquals( 'array', $schemaIndex->root['type'] );
		$nodename = $schema['title'] ?? 'Root node';
		$rootschema = $schemaIndex->newRef( $schema, null, null, $nodename );
		$rootjson = new JsonTreeRef( $data, null, null, $nodename, $rootschema );
		$this->assertEquals( 'Address Book', $rootjson->getTitle() );
	}

	public function testJsonSchemaValidateAddressExample() {
		$jsonfile = 'example/addressexample.json';
		$schemafile = 'schemas/addressbookschema.json';
		$jsonref = self::loadJsonRef( $jsonfile, $schemafile );
		$this->assertTrue( $jsonref->validate() );
	}

	public function testJsonSchemaValidateSuccessfulExample() {
		$jsonfile = 'tests/phpunit/data/2/test5.json';
		$schemafile = 'tests/phpunit/data/2/schematest5.json';
		$jsonref = self::loadJsonRef( $jsonfile, $schemafile );
		$this->assertTrue( $jsonref->validate() );
	}

	public function testJsonSchemaValidateBadIdref() {
		$jsonfile = 'tests/phpunit/data/1/test5.json';
		$schemafile = 'tests/phpunit/data/1/schematest5.json';
		$jsonref = self::loadJsonRef( $jsonfile, $schemafile );
		$this->expectException( JsonSchemaException::class );
		$jsonref->validate();
	}

	public function testJsonSchemaValidateBadData() {
		$jsonfile = 'tests/phpunit/data/1/test5.json';
		$schemafile = 'tests/phpunit/data/2/schematest5.json';
		$jsonref = self::loadJsonRef( $jsonfile, $schemafile );
		$this->expectException( JsonSchemaException::class );
		$jsonref->validate();
	}

	public function testJsonSchemaValidateTestSchema2() {
		$jsonfile = 'tests/phpunit/data/2/schematest5.json';
		$schemafile = 'schemas/schemaschema.json';
		$jsonref = self::loadJsonRef( $jsonfile, $schemafile );
		$this->assertTrue( $jsonref->validate() );
	}

	public function testJsonSchemaValidateGoodLocation() {
		$jsonfile = 'tests/phpunit/data/validlocation.json';
		$schemafile = 'tests/phpunit/data/schemalocation.json';
		$jsonref = self::loadJsonRef( $jsonfile, $schemafile );
		$this->assertTrue( $jsonref->validate() );
	}

	public function testJsonSchemaValidateMissingFieldLocation() {
		$jsonfile = 'tests/phpunit/data/missingfieldlocation.json';
		$schemafile = 'tests/phpunit/data/schemalocation.json';
		$jsonref = self::loadJsonRef( $jsonfile, $schemafile );
		$this->expectException( JsonSchemaException::class );
		$jsonref->validate();
	}

	public function testJsonSchemaValidateBadDataLocation() {
		$jsonfile = 'tests/phpunit/data/invalidlocation.json';
		$schemafile = 'tests/phpunit/data/schemalocation.json';
		$jsonref = self::loadJsonRef( $jsonfile, $schemafile );
		$this->expectException( JsonSchemaException::class );
		$jsonref->validate();
	}

	public function testJsonSchemaValidateTestLocationSchema() {
		$jsonfile = 'tests/phpunit/data/schemalocation.json';
		$schemafile = 'schemas/schemaschema.json';
		$jsonref = self::loadJsonRef( $jsonfile, $schemafile );
		$this->assertTrue( $jsonref->validate() );
	}

	public function testExtensionFieldGetTitle() {
		$jsonfile = 'tests/phpunit/data/ab.json';
		$schemafile = 'schemas/openschema.json';
		$jsonref = self::loadJsonRef( $jsonfile, $schemafile );
		// if the titles for two different extension fields is the same, that
		// means the titles are probably pretty useless.
		$atitle = $jsonref->getMappingChildRef( 'a' )->getTitle();
		$btitle = $jsonref->getMappingChildRef( 'b' )->getTitle();
		$this->assertNotEquals( $atitle, $btitle );
	}

	public function testJsonSchemaValidateEmptyMap() {
		$jsonfile = 'tests/phpunit/data/emptymap.json';
		$schemafile = 'schemas/datatype-example-schema.json';
		$jsonref = self::loadJsonRef( $jsonfile, $schemafile );
		$this->assertTrue( $jsonref->validate() );
	}

	public function testJsonSchemaValidateInteger() {
		$jsonfile = 'tests/phpunit/data/inttest.json';
		$schemafile = 'schemas/datatype-example-schema.json';
		$jsonref = self::loadJsonRef( $jsonfile, $schemafile );
		$this->assertTrue( $jsonref->validate() );
	}

	public function testJsonSchemaValidateArrayObj() {
		$jsonfile = 'tests/phpunit/data/arrayobj.json';
		$schemafile = 'schemas/openschema.json';
		$jsonref = self::loadJsonRef( $jsonfile, $schemafile );
		$this->assertTrue( $jsonref->validate() );
	}

	public function testTypeBooleanFreeformField() {
		$jsonfile = 'tests/phpunit/data/boolean.json';
		$schemafile = 'schemas/openschema.json';
		$jsonref = self::loadJsonRef( $jsonfile, $schemafile );
		$this->assertTrue( $jsonref->validate() );
	}

	/**
	 * @dataProvider getStandardSchemas
	 * @param string $jsonfile
	 */
	public function testJsonSchemaValidateStandardSchemas( $jsonfile ) {
		$schemaschema = 'schemas/schemaschema.json';
		$jsonref = self::loadJsonRef( $jsonfile, $schemaschema );
		$this->assertTrue( $jsonref->validate() );
	}

	public function getStandardSchemas() {
		return [
			[ 'schemas/addressbookschema.json' ],
			[ 'schemas/datatype-example-schema.json' ],
			[ 'schemas/jsondata-config-schema.json' ],
			[ 'schemas/openschema.json' ],
			[ 'schemas/schemaschema.json' ],
			[ 'schemas/simpleaddr-schema.json' ],
		];
	}

	/**
	 * @dataProvider getPredefinedSchemas
	 * @param string $schema
	 */
	public function testReadJsonFromPredefined( $schema ) {
		$this->assertIsString( JsonData::readJsonFromPredefined( $schema ) );
	}

	public function getPredefinedSchemas() {
		global $wgJsonDataPredefinedData;

		return array_map(
			static function ( $el ) {
				return [ $el ];
			},
			array_keys( $wgJsonDataPredefinedData )
		);
	}
}
