<?php
/**
 * Mapgadget Fixture
 */
class MapgadgetFixture extends CakeTestFixture {

/**
 * Fields
 *
 * @var array
 */
	public $fields = array(
		'id' => array('type' => 'integer', 'null' => false, 'default' => null, 'unsigned' => false, 'key' => 'primary'),
		'map_id' => array('type' => 'integer', 'null' => false, 'default' => null, 'unsigned' => false),
		'x' => array('type' => 'integer', 'null' => false, 'default' => '0', 'unsigned' => false),
		'y' => array('type' => 'integer', 'null' => false, 'default' => '0', 'unsigned' => false),
		'limit' => array('type' => 'integer', 'null' => true, 'default' => '0', 'unsigned' => false),
		'gadget' => array('type' => 'string', 'null' => false, 'default' => null, 'length' => 128, 'collate' => 'utf8_swedish_ci', 'charset' => 'utf8'),
		'type' => array('type' => 'string', 'null' => false, 'default' => null, 'length' => 20, 'collate' => 'utf8_swedish_ci', 'charset' => 'utf8'),
		'object_id' => array('type' => 'integer', 'null' => false, 'default' => null, 'unsigned' => false),
		'transparent_background' => array('type' => 'integer', 'null' => false, 'default' => '0', 'length' => 5, 'unsigned' => false),
		'created' => array('type' => 'datetime', 'null' => false, 'default' => null),
		'modified' => array('type' => 'datetime', 'null' => false, 'default' => null),
		'indexes' => array(
			'PRIMARY' => array('column' => 'id', 'unique' => 1)
		),
		'tableParameters' => array('charset' => 'utf8', 'collate' => 'utf8_swedish_ci', 'engine' => 'InnoDB')
	);

/**
 * Records
 *
 * @var array
 */
	public $records = array(
		array(
			'id' => 1,
			'map_id' => 1,
			'x' => 1,
			'y' => 1,
			'limit' => 1,
			'gadget' => 'Lorem ipsum dolor sit amet',
			'type' => 'Lorem ipsum dolor ',
			'object_id' => 1,
			'transparent_background' => 1,
			'created' => '2017-01-31 14:36:44',
			'modified' => '2017-01-31 14:36:44'
		),
	);

}
