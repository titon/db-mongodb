<?php
/**
 * @copyright	Copyright 2010-2013, The Titon Project
 * @license		http://opensource.org/licenses/bsd-license.php
 * @link		http://titon.io
 */

namespace Titon\Model\Mongo;

use Titon\Model\Driver\AbstractDriver;
use Titon\Model\Exception\InvalidQueryException;
use Titon\Model\Exception\UnsupportedQueryStatementException;
use Titon\Model\Model;
use Titon\Model\Query;
use \MongoClient;

/**
 * A driver that represents the MongoDB database.
 *
 * @package Titon\Model\Mongo
 * @method \MongoClient getConnection()
 * @method \Titon\Model\Mongo\MongoDialect getDialect()
 */
class MongoDriver extends AbstractDriver {

	/**
	 * Configuration.
	 */
	protected $_config = [
		'host' => MongoClient::DEFAULT_HOST,
		'port' => MongoClient::DEFAULT_PORT,
		'flags' => [
			'connect' => true,
			'w' => 1
		]
	];

	/**
	 * Last inserted ID.
	 *
	 * @type \MongoId
	 */
	protected $_lastID;

	/**
	 * Set the dialect.
	 */
	public function initialize() {
		$this->setDialect(new MongoDialect($this));
	}

	/**
	 * {@inheritdoc}
	 */
	public function commitTransaction() {
		return true;
	}

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
	public function describeDatabase($database = null) {
		$db = $this->getConnection()->selectDB($database ?: $this->getDatabase());
		$schema = [];

		foreach ($db->getCollectionNames() as $collection) {
			$schema[$collection] = $db->command(['collStats' => $collection]);
		}

		return $schema;
	}

	/**
	 * {@inheritdoc}
	 */
	public function describeTable($table) {
		return []; // MongoDB is schemaless
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
	 * {@inheritdoc}
	 */
	public function escape($value) {
		return $value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getLastInsertID(Model $model) {
		return (string) $this->_lastID;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSupportedTypes() {
		return [
			'string' => 'Titon\Model\Driver\Type\StringType',
			'integer' => 'Titon\Model\Driver\Type\IntType',
			'number' => 'Titon\Model\Driver\Type\IntType',
			'array' => 'Titon\Model\Mongo\Type\ArrayType',
			'object' => 'Titon\Model\Mongo\Type\ObjectType',
			'boolean' => 'Titon\Model\Driver\Type\BooleanType',
			'float' => 'Titon\Model\Driver\Type\FloatType',
			'double' => 'Titon\Model\Driver\Type\DoubleType',
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function isEnabled() {
		return extension_loaded('mongo');
	}

	/**
	 * {@inheritdoc}
	 *
	 * @throws \Titon\Model\Exception\InvalidQueryException
	 */
	public function query($query, array $params = []) {
		$storage = $this->getStorage();
		$cacheKey = null;
		$cacheLength = null;

		// Determine cache key and lengths
		if ($query instanceof Query) {
			$cacheKey = $query->getCacheKey();
			$cacheLength = $query->getCacheLength();

		} else {
			throw new InvalidQueryException('Query must be a Titon\Model\Query instance');
		}

		// Use the storage engine first
		if ($cacheKey) {
			if ($storage && $storage->has($cacheKey)) {
				return $storage->get($cacheKey);

			// Fallback to driver cache
			// This is used to cache duplicate queries
			} else if ($this->hasCache($cacheKey)) {
				return $this->getCache($cacheKey);
			}
		}

		// Execute the query using the dialect
		$dialect = $this->getDialect();
		$db = $this->getConnection()->selectDB($this->getDatabase());

		if ($query->getType() === Query::CREATE_TABLE) {
			$response = $dialect->buildCreateTable($db, $query);

		} else {
			$type = $query->getType();
			$method = 'build' . ucfirst($type);
			$startTime = microtime();

			if (!method_exists($dialect, $method)) {
				throw new UnsupportedQueryStatementException(sprintf('Query statement %s does not exist or has not been implemented', $type));
			}

			$response = call_user_func_array([$dialect, $method], [$db->selectCollection($query->getTable()), $query]);
			$response['startTime'] = $startTime;
		}

		// Gather and log result
		if (is_array($response) && isset($response['id'])) {
			$this->_lastID = $response['id'];
		} else {
			$this->_lastID = null;
		}

		$this->_result = new MongoResult($response, $query);

		$this->logQuery($this->_result);

		// Return and cache result
		if ($cacheKey) {
			if ($storage) {
				$storage->set($cacheKey, $this->_result, $cacheLength);
			} else {
				$this->setCache($cacheKey, $this->_result);
			}
		}

		return $this->_result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function rollbackTransaction() {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function startTransaction() {
		return true;
	}

}