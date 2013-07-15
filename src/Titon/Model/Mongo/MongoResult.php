<?php
/**
 * @copyright	Copyright 2010-2013, The Titon Project
 * @license		http://opensource.org/licenses/bsd-license.php
 * @link		http://titon.io
 */

namespace Titon\Model\Mongo;

use Titon\Model\Query\Result\AbstractResult;
use Titon\Model\Query;
use \MongoCursor;

/**
 * Accepts a MongoCursor or MongoDB command response.
 *
 * @package Titon\Model\Mongo
 */
class MongoResult extends AbstractResult {

	/**
	 * Cursor returned from select statements.
	 *
	 * @type \MongoCursor
	 */
	protected $_cursor;

	/**
	 * Response from a MongoDB command.
	 *
	 * @type array
	 */
	protected $_response;

	/**
	 * Store the result of a MongoDB command, either a MongoCursor or array response.
	 *
	 * @param \MongoCursor|array $response
	 * @param \Titon\Model\Query $query
	 */
	public function __construct($response, Query $query) {
		parent::__construct($query);

		if ($response instanceof MongoCursor) {
			$this->_cursor = $response;
			$this->_params = $response->info();

			if ($explain = $response->explain()) {
				$this->_time = $explain['millis'];
			}
		} else {
			$this->_response = $response;
			$this->_params = $response['params'] + ['ns' => $query->getTable()];
			$this->_executed = isset($response['ok']);
			$this->_success = $this->hasExecuted() ? (bool) $response['ok'] : false;

			if (in_array($query->getType(), [Query::UPDATE, Query::INSERT, Query::DELETE])) {
				$this->_count = isset($response['n']) ? (int) $response['n'] : 0;
			} else {
				$this->_count = 1;
			}

			$this->_time = number_format(microtime() - $response['startTime'], 5);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function close() {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function count() {
		if ($this->_cursor) {
			return $this->_cursor->count();
		}

		return 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function execute() {
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function fetch() {
		$results = $this->fetchAll();

		if (isset($results[0])) {
			return $results[0];
		}

		return $results;
	}

	/**
	 * {@inheritdoc}
	 */
	public function fetchAll() {
		$cursor = $this->_cursor;
		$results = [];

		if (!$cursor) {
			return $results;
		}

		while ($cursor->hasNext()) {
			$results[] = $cursor->current();

			$cursor->next();
		}

		return $results;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getStatement() {
		$query = $this->getQuery();
		$params = $this->getParams();
		$attributes = json_encode($query->getAttributes());
		$statement = 'db.' . $params['ns'] . '.';

		switch ($query->getType()) {
			case Query::INSERT:
				$statement .= sprintf('insert(%s, %s);', json_encode($params['values']), $attributes);
			break;
			case Query::MULTI_INSERT:
				$statement .= sprintf('batchInsert(%s, %s);', json_encode($params['values']), $attributes);
			break;
			case Query::SELECT:
				$statement .= sprintf('find(%s, %s);', json_encode($params['query']), json_encode($params['fields']));
			break;
			case Query::UPDATE:
				$statement .= sprintf('update(%s, %s, %s);', json_encode($params['where']), json_encode($params['fields']), $attributes);
			break;
			case Query::DELETE:
				$statement .= sprintf('remove(%s, %s);', json_encode($params['where']), $attributes);
			break;
			case Query::TRUNCATE:
				$statement .= sprintf('remove([], %s); (truncate)', $attributes);
			break;
			case Query::CREATE_TABLE:
				$statement = sprintf('db.createCollection(%s, %s);', json_encode($params['name']), $attributes);
			break;
			case Query::CREATE_INDEX:
				$statement .= sprintf('ensureIndex(%s, %s);', json_encode($params['fields']), $attributes);
			break;
			case Query::DROP_TABLE:
				$statement .= 'drop();';
			break;
			case Query::DROP_INDEX:
				$statement .= sprintf('deleteIndex(%s);', json_encode($params['fields']));
			break;
			default:
				$statement = '(unknown statement)';
			break;
		}

		return $statement;
	}

	/**
	 * {@inheritdoc}
	 */
	public function save() {
		if ($this->isSuccessful()) {
			return $this->_count;
		}

		return false;
	}

}