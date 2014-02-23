<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Db\Mongo;

use Titon\Db\Driver\AbstractDriver;
use Titon\Db\Exception\InvalidQueryException;
use Titon\Db\Exception\MissingDriverException;
use Titon\Db\Exception\UnsupportedQueryStatementException;
use Titon\Db\Repository;
use Titon\Db\Mongo\Exception\MissingServersException;
use Titon\Db\Query;
use \MongoClient;

/**
 * A driver that represents the MongoDB database.
 *
 * @package Titon\Db\Mongo
 * @method \MongoClient getConnection()
 * @method \Titon\Db\Mongo\MongoDialect getDialect()
 */
class MongoDriver extends AbstractDriver {

    /**
     * Configuration.
     *
     * @type array {
     *      @type array $servers        List of servers to connect to or to use in a replica set
     *      @type string $replicaSet    Name of the replica set instance
     * }
     */
    protected $_config = [
        'host' => MongoClient::DEFAULT_HOST,
        'port' => MongoClient::DEFAULT_PORT,
        'servers' => [],
        'replicaSet' => '',
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
     * @throws \Titon\Db\Exception\MissingDriverException
     * @throws \Titon\Db\Mongo\Exception\MissingServersException
     */
    public function connect() {
        if ($this->isConnected()) {
            return true;
        }

        if (!$this->isEnabled()) {
            throw new MissingDriverException('mongodb driver extension is not enabled');
        }

        $options = [];

        if ($db = $this->getDatabase()) {
            $options['db'] = $db;
        }

        if ($user = $this->getUser()) {
            $options['username'] = $user;
        }

        if ($pass = $this->getPassword()) {
            $options['password'] = $pass;
        }

        if ($rSet = $this->getConfig('replicaSet')) {
            if (!$this->getConfig('servers')) {
                throw new MissingServersException('A list of servers is required for replica set functionality');
            }

            $options['replicaSet'] = $rSet;
        }

        $connection = new MongoClient($this->getServer(), $options + $this->getConfig('flags'));

        $this->_connections[$this->getContext()] = $connection;

        return $connection->connected;
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
    public function disconnect($flush = false) {
        $this->reset();

        if ($this->isConnected()) {
            /** @type \MongoClient $connection */
            foreach ($this->_connections as $context => $connection) {
                if ($flush || $context === $this->getContext()) {
                    $connection->close(true);
                }
            }

            return true;
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
     *
     * @param array|\Titon\Db\Query $query
     * @throws \Titon\Db\Exception\InvalidQueryException
     */
    public function executeQuery($query, array $params = []) {
        $storage = $this->getStorage();
        $cacheKey = null;
        $cacheLength = null;

        // Determine cache key and lengths
        if ($query instanceof Query) {
            $cacheKey = $query->getCacheKey();
            $cacheLength = $query->getCacheLength();

        } else if (!is_array($query)) {
            throw new InvalidQueryException('Query must be a command array or a Titon\Db\Query instance');
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
        $db = $this->getConnection()->selectDB($this->getDatabase());
        $dialect = $this->getDialect();
        $startTime = microtime();

        // Direct query command
        if (is_array($query)) {
            $response = $db->command($query, $params);

        // Using query object
        } else {
            if ($query->getType() === Query::CREATE_TABLE) {
                $response = $dialect->executeCreateTable($db, $query);

            } else {
                $type = $query->getType();
                $method = 'execute' . ucfirst($type);

                if (!method_exists($dialect, $method)) {
                    throw new UnsupportedQueryStatementException(sprintf('Query statement %s does not exist or has not been implemented', $type));
                }

                $response = call_user_func_array([$dialect, $method], [$db->selectCollection($query->getTable()), $query]);
            }
        }

        // Gather and log result
        if (is_array($response)) {
            $response['startTime'] = $startTime;

            if (isset($response['id'])) {
                $this->_lastID = $response['id'];
            }
        } else {
            $this->_lastID = null;
        }

        if (is_array($query)) {
            $response['command'] = $query;

            $this->_result = new MongoResultSet($response);
        } else {
            $this->_result = new MongoResultSet($response, $query);
        }

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
    public function getLastInsertID(Repository $table) {
        return $this->_lastID;
    }

    /**
     * Build and return the server connection.
     *
     * @return string
     */
    public function getServer() {
        $server = 'mongodb://';

        if ($servers = $this->getConfig('servers')) {
            $server .= implode(',', $servers);

        } else {
            if ($socket = $this->getSocket()) {
                $server .= $socket;
            } else {
                $server .= $this->getHost() . ':' . $this->getPort();
            }
        }

        return $server;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedTypes() {
        return [
            'int' => 'Titon\Db\Driver\Type\IntType',
            'int32' => 'Titon\Db\Mongo\Type\Int32Type',
            'int64' => 'Titon\Db\Mongo\Type\Int64Type',
            'integer' => 'Titon\Db\Driver\Type\IntType',
            'string' => 'Titon\Db\Driver\Type\StringType',
            'number' => 'Titon\Db\Driver\Type\IntType',
            'array' => 'Titon\Db\Mongo\Type\ArrayType',
            'object' => 'Titon\Db\Mongo\Type\ObjectType',
            'boolean' => 'Titon\Db\Driver\Type\BooleanType',
            'float' => 'Titon\Db\Driver\Type\FloatType',
            'double' => 'Titon\Db\Driver\Type\DoubleType',
            'date' => 'Titon\Db\Mongo\Type\DatetimeType',
            'time' => 'Titon\Db\Mongo\Type\DatetimeType',
            'datetime' => 'Titon\Db\Mongo\Type\DatetimeType',
            'timestamp' => 'Titon\Db\Mongo\Type\DatetimeType',
            'blob' => 'Titon\Db\Mongo\Type\BlobType',
            'binary' => 'Titon\Db\Mongo\Type\BlobType',
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
     */
    public function listTables($database = null) {
        return $this->getConnection()->selectDB($database ?: $this->getDatabase())->getCollectionNames();
    }

    /**
     * {@inheritdoc}
     */
    public function newQuery($type) {
        return new MongoQuery($type);
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