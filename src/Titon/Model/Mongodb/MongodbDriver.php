<?php
/**
 * @copyright	Copyright 2010-2013, The Titon Project
 * @license		http://opensource.org/licenses/bsd-license.php
 * @link		http://titon.io
 */

namespace Titon\Model\MongoDb;

use Titon\Model\Driver\AbstractDriver;

/**
 * A driver that represents the MongoDB database and uses PDO.
 *
 * @package Titon\Model\MongoDb
 */
class MongoDbDriver extends AbstractDriver {

	/**
	 * Configuration.
	 */
	protected $_config = [];

	/**
	 * Set the dialect.
	 */
	public function initialize() {
		$this->setDialect(new MongodbDialect($this));
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSupportedTypes() {
		return [
			'string' => 'Titon\Model\Driver\Type\StringType',
			'number' => 'Titon\Model\Driver\Type\IntType',
			'array' => 'Titon\Model\MongoDb\Type\ArrayType',
			'object' => 'Titon\Model\MongoDb\Type\ObjectType',
		] + parent::getSupportedTypes();
	}

	/**
	 * {@inheritdoc}
	 */
	public function isEnabled() {
		return extension_loaded('mongo');
	}

}