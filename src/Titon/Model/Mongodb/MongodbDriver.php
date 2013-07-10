<?php
/**
 * @copyright	Copyright 2010-2013, The Titon Project
 * @license		http://opensource.org/licenses/bsd-license.php
 * @link		http://titon.io
 */

namespace Titon\Model\MongoDb;

use Titon\Model\Driver\AbstractDriver;
use \MongoClient;

/**
 * A driver that represents the MongoDB database and uses PDO.
 *
 * @package Titon\Model\MongoDb
 * @method \MongoClient getConnection()
 */
class MongoDbDriver extends AbstractDriver {

	/**
	 * Configuration.
	 */
	protected $_config = [
		'port' => 27017,
		'flags' => [
			'connect' => true
		]
	];

	/**
	 * Connect to the Mongo database.
	 *
	 * @return bool
	 */
	public function connect() {
		if ($this->isConnected()) {
			return true;
		}

		$server = 'mongodb://';

		if ($socket = $this->getSocket()) {
			$server .= $socket;
		} else {
			if ($user = $this->getUser()) {
				$server .= $user . ':' . $this->getPassword() . '@';
			}

			$server .= $this->getHost() . ':' . $this->getPort();
		}

		$this->_connection = new MongoClient($server, $this->config->flags);
		$this->_connected = $this->_connection->connected;

		return $this->_connected;
	}

	/**
	 * {@inheritdoc}
	 */
	public function disconnect() {
		$this->reset();

		if ($this->isConnected()) {
			return $this->getConnection()->close(true);
		}

		return false;
	}

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