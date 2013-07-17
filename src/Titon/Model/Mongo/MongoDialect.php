<?php
/**
 * @copyright	Copyright 2010-2013, The Titon Project
 * @license		http://opensource.org/licenses/bsd-license.php
 * @link		http://titon.io
 */

namespace Titon\Model\Mongo;

use Titon\Model\Driver\Dialect\AbstractDialect;
use Titon\Model\Exception\InvalidQueryException;
use Titon\Model\Exception\UnsupportedFeatureException;
use Titon\Model\Query;
use Titon\Model\Query\Expr;
use Titon\Model\Query\Func;
use Titon\Model\Query\Predicate;
use Titon\Model\Query\SubQuery;
use Titon\Utility\Hash;
use \MongoCollection;
use \MongoCode;
use \MongoId;
use \MongoDB;
use \MongoRegex;
use \Closure;

/**
 * Inherit the default dialect rules and override for MongoDB specific syntax.
 *
 * @link http://docs.mongodb.org/manual/reference/sql-comparison/
 *
 * @package Titon\Model\Mongo
 */
class MongoDialect extends AbstractDialect {

	const BIT = 'bit';
	const INC = 'inc';
	const ISOLATED = 'isolated';
	const NOT = 'not';
	const NOR = 'nor';
	const REGEX = 'regex';
	const RENAME = 'rename';
	const SET = 'set';
	const SET_ON_INSERT = 'setOnInsert';
	const SIZE = 'size';
	const TYPE = 'type';
	const REMOVE = 'unset';

	const TYPE_DOUBLE = 1;
	const TYPE_STRING = 2;
	const TYPE_OBJECT = 3;
	const TYPE_ARRAY = 4;
	const TYPE_BINARY = 5;
	const TYPE_UNDEFINED = 6;
	const TYPE_OBJECT_ID = 7;
	const TYPE_BOOLEAN = 8;
	const TYPE_DATE = 9;
	const TYPE_NULL = 10;
	const TYPE_REGEX = 11;
	const TYPE_JAVASCRIPT = 12;
	const TYPE_SYMBOL = 14;
	const TYPE_JS_SCOPE = 15;
	const TYPE_INT_32 = 16;
	const TYPE_TIMESTAMP = 17;
	const TYPE_INT_64 = 18;
	const TYPE_MIN_KEY = 255;
	const TYPE_MAX_KEY = 127;

	/**
	 * Configuration.
	 *
	 * @type array
	 */
	protected $_config = [
		'quoteCharacter' => ''
	];

	/**
	 * {@inheritdoc}
	 */
	protected $_clauses = [
		self::ALL			=> '$all',
		self::ALSO			=> '$and',
		self::BIT			=> '$bit',
		self::EITHER		=> '$or',
		self::EXISTS		=> '$exists',
		self::IN			=> '$in',
		self::INC			=> '$inc',
		self::IS_NOT_NULL	=> '$ne',
		self::ISOLATED		=> '$isolated',
		self::NOR			=> '$nor',
		self::NOT			=> '$not',
		self::NOT_IN		=> '$nin',
		self::REGEX			=> '$regex',
		self::REGEXP		=> '$regex',
		self::REMOVE		=> '$unset',
		self::RENAME		=> '$rename',
		self::SET			=> '$set',
		self::SET_ON_INSERT => '$setOnInsert',
		self::SIZE			=> '$size',
		self::TYPE			=> '$type',
		self::WHERE			=> '$where',
		'>'					=> '$gt',
		'>='				=> '$gte',
		'<'					=> '$lt',
		'<='				=> '$lte',
		'!='				=> '$ne',
		'%'					=> '$mod',
	];

	/**
	 * {@inheritdoc}
	 */
	protected $_keywords = [];

	/**
	 * {@inheritdoc}
	 */
	protected $_statements = [];

	/**
	 * {@inheritdoc}
	 */
	protected $_attributes = [];

	/**
	 * Execute the ensureIndex() method to create a collection index.
	 *
	 * @param \MongoCollection $collection
	 * @param \Titon\Model\Query $query
	 * @return array
	 */
	public function executeCreateIndex(MongoCollection $collection, Query $query) {
		$fields = $this->formatFields($query);

		$response = $collection->ensureIndex($fields, $query->getAttributes() + ['w' => 1]);
		$response['params'] = [
			'fields' => $fields
		];

		return $response;
	}

	/**
	 * Execute the createCollection() method to create a new collection.
	 *
	 * @param \MongoDB $db
	 * @param \Titon\Model\Query $query
	 * @return array
	 */
	public function executeCreateTable(MongoDB $db, Query $query) {
		$name = $query->getTable();

		if ($schema = $query->getSchema()) {
			$name = $schema->getTable() ?: $name;
		}

		if ($db->createCollection($name, $query->getAttributes())) {
			return [
				'ok' => 1,
				'n' => 1,
				'params' => ['name' => $name]
			];
		}

		return [
			'ok' => 0,
			'err' => 'Failed to create collection'
		];
	}

	/**
	 * Execute the remove() method to delete collection rows.
	 *
	 * @param \MongoCollection $collection
	 * @param \Titon\Model\Query $query
	 * @return array
	 */
	public function executeDelete(MongoCollection $collection, Query $query) {
		$where = $this->formatWhere($query->getWhere());

		$query->attribute('justOne', ($query->getLimit() == 1));

		$response = $collection->remove($where, $query->getAttributes() + ['w' => 1]);
		$response['params'] = [
			'where' => $where
		];

		return $response;
	}

	/**
	 * Execute the deleteIndex() method to delete a collection index.
	 *
	 * @param \MongoCollection $collection
	 * @param \Titon\Model\Query $query
	 * @return array
	 */
	public function executeDropIndex(MongoCollection $collection, Query $query) {
		$fields = $this->formatFields($query);

		$response = $collection->deleteIndex($fields, $query->getAttributes() + ['w' => 1]);
		$response['params'] = [
			'fields' => $fields
		];

		return $response;
	}

	/**
	 * Execute the drop() method to delete a collection.
	 *
	 * @param \MongoCollection $collection
	 * @param \Titon\Model\Query $query
	 * @return array
	 */
	public function executeDropTable(MongoCollection $collection, Query $query) {
		$response = $collection->drop();
		$response['params'] = [];

		return $response;
	}

	/**
	 * Execute the insert() method to create a record. Grab the new record ID from the response.
	 *
	 * @param \MongoCollection $collection
	 * @param \Titon\Model\Query $query
	 * @return array
	 */
	public function executeInsert(MongoCollection $collection, Query $query) {
		$values = $this->formatValues($query);

		$response = $collection->insert($values, $query->getAttributes() + ['w' => 1]);
		$response['params'] = [
			'values' => $values
		];

		if (isset($values['_id']) && $response['ok']) {
			$response['id'] = $values['_id'];
		}

		return $response;
	}

	/**
	 * Execute the batchInsert() method to create multiple record.
	 *
	 * @param \MongoCollection $collection
	 * @param \Titon\Model\Query $query
	 * @return array
	 */
	public function executeMultiInsert(MongoCollection $collection, Query $query) {
		$values = $this->formatValues($query);

		$response =  $collection->batchInsert($values, $query->getAttributes() + ['w' => 1]);
		$response['params'] = [
			'values' => $values
		];

		if ($response['ok']) {
			$response['n'] = count($values);
		}

		return $response;
	}

	/**
	 * Execute the find() method to select records and return a cursor object.
	 * If a group by is declared, execute the group() method instead.
	 *
	 * @param \MongoCollection $collection
	 * @param \Titon\Model\Query $query
	 * @return \MongoCursor|array
	 */
	public function executeSelect(MongoCollection $collection, Query $query) {
		$where = $this->formatWhere($query->getWhere());

		if ($groupBy = $query->getGroupBy()) {
			$groupBy = $this->formatGroupBy($groupBy);

			$response = $collection->group($groupBy, ['items' => []], 'function(item, results) { results.items.push(item); }', [
				'condition' => $where
			]);

			$response['params'] = [
				'where' => $where,
				'groupBy' => $groupBy
			];

			return $response;
		}

		// Regular find using a cursor
		$cursor = $collection->find($where, $this->formatFields($query));

		if ($orderBy = $query->getOrderBy()) {
			$cursor->sort($this->formatOrderBy($orderBy));
		}

		if ($offset = $query->getOffset()) {
			$cursor->skip($this->formatLimitOffset(null, $offset));
		}

		if ($limit = $query->getLimit()) {
			$cursor->limit($this->formatLimit($limit));
		}

		return $cursor;
	}

	/**
	 * MongoDB doesn't support truncation, so simply remove all records.
	 *
	 * @param \MongoCollection $collection
	 * @param \Titon\Model\Query $query
	 * @return array
	 */
	public function executeTruncate(MongoCollection $collection, Query $query) {
		$response = $collection->remove([], $query->getAttributes() + ['w' => 1]);
		$response['params'] = [];

		return $response;
	}

	/**
	 * Execute the update() method to update a record or multiple records.
	 *
	 * @param \MongoCollection $collection
	 * @param \Titon\Model\Query $query
	 * @return array
	 */
	public function executeUpdate(MongoCollection $collection, Query $query) {
		$where = $this->formatWhere($query->getWhere());
		$fields = $this->formatFields($query);

		$query->attribute([
			'upsert' => false,
			'multiple' => ($query->getLimit() != 1) // multiple in PHP, multi in Mongo
		]);

		$response = $collection->update($where, $fields, $query->getAttributes() + ['w' => 1]);
		$response['params'] = [
			'where' => $where,
			'fields' => $fields
		];

		return $response;
	}

	/**
	 * {@inheritdoc}
	 */
	public function formatDefault($value) {
		if ($value instanceof Closure) {
			$value = $value($this);
		} else {
			$value = $this->getDriver()->escape($value);
		}

		return $value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function formatExpression(Expr $expr) {
		$field = $expr->getField();
		$operator = $expr->getOperator();
		$value = $expr->getValue();

		// Auto-wrap ID fields
		if ($field === '_id' && !($value instanceof MongoId)) {
			$value = new MongoId($value);
		}

		switch ($operator) {
			case '=':
			case self::IS_NULL:
				// Do nothing
			break;
			case self::BETWEEN:
				$value = [
					$this->getClause('>=') => $value[0],
					$this->getClause('<=') => $value[1]
				];
			break;
			case self::NOT_BETWEEN:
				$value = [
					[$field => [$this->getClause('<') => $value[0]]],
					[$field => [$this->getClause('>') => $value[1]]]
				];

				return [$this->getClause(self::EITHER) => $value];
			break;
			case self::LIKE:
			case self::REGEXP:
			case self::RLIKE:
			case self::NOT_LIKE:
			case self::NOT_REGEXP:
			case self::REGEX:
			case '$regex':
				if (!($value instanceof MongoRegex)) {
					$value = new MongoRegex($value);
				}

				if ($operator === Expr::NOT_LIKE || $operator === Expr::NOT_REGEXP) {
					$value = [$this->getClause(self::NOT) => $value];
				}
			break;
			case self::WHERE:
			case '$where':
				if (is_string($value)) {
					$value = new MongoCode($value);
				}

				if (!($value instanceof MongoCode)) {
					throw new InvalidQueryException('When using $where the value must be an instance of MongoCode');
				}

				return [$this->getClause(self::WHERE) => $value];
			break;
			default:
				if (substr($operator, 0, 1) !== '$') {
					$operator = $this->getClause($operator);
				}

				$value = [$operator => $value];
			break;
		}

		return [$field => $value];
	}

	/**
	 * {@inheritdoc}
	 */
	public function formatFields(Query $query) {
		switch ($query->getType()) {
			case Query::CREATE_INDEX:
				return $this->formatOrderBy($query->getFields());
			break;

			case Query::UPDATE:
				$fields = $query->getFields();
				$clean = [];
				$set = [];

				if (empty($fields)) {
					throw new InvalidQueryException('Missing field data for update query');
				}

				foreach ($fields as $field => $value) {
					if (substr($field, 0, 1) === '$') {
						$clean[$field] = $value;
					} else {
						$set[$field] = $value;
					}
				}

				if ($set) {
					if (isset($clean['$set'])) {
						$clean['$set'] = array_merge($clean['$set'], $set);
					} else {
						$clean['$set'] = $set;
					}
				}

				return $clean;
			break;

			case Query::SELECT:
				$fields = $query->getFields();

				if ($fields) {
					$clean = [];

					foreach ($fields as $field) {
						if ($field instanceof Func) {
							// COUNT(), etc
							continue;
						} else {
							$clean[$field] = 1;
						}
					}

					$fields = $clean;

					if (isset($fields['id'])) {
						$fields['_id'] = $fields['id'];
						unset($fields['id']);
					}

					if (empty($fields['_id'])) {
						$fields['_id'] = 0;
					}
				}

				return $fields;
			break;

			default:
				return $query->getFields();
			break;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function formatGroupBy(array $groupBy) {
		return $this->formatOrderBy($groupBy);
	}

	/**
	 * {@inheritdoc}
	 */
	public function formatHaving(Predicate $having) {
		return $this->formatPredicate($having);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @throws \Titon\Model\Exception\UnsupportedFeatureException
	 */
	public function formatJoins(array $joins) {
		throw new UnsupportedFeatureException('MongoDB does not support joins');
	}

	/**
	 * {@inheritdoc}
	 */
	public function formatLimit($limit) {
		return $limit;
	}

	/**
	 * {@inheritdoc}
	 */
	public function formatLimitOffset($limit, $offset = 0) {
		return $offset;
	}

	/**
	 * {@inheritdoc}
	 */
	public function formatOrderBy(array $orderBy) {
		$output = [];

		foreach ($orderBy as $field => $direction) {
			if (is_numeric($field)) {
				$field = $direction;
				$direction = null;
			}

			if (is_numeric($direction)) {
				$output[$field] = $direction;

			} else if ($direction === self::DESC) {
				$output[$field] = MongoCollection::DESCENDING;

			} else {
				$output[$field] = MongoCollection::ASCENDING;
			}
		}

		return $output;
	}

	/**
	 * {@inheritdoc}
	 */
	public function formatPredicate(Predicate $predicate) {
		$output = [];

		// Or needs to be wrapped in $or arrays
		if ($predicate->getType() === Predicate::EITHER) {
			return $this->formatSubPredicate($predicate);
		}

		// Base $and can use a simple array structure
		foreach ($predicate->getParams() as $param) {
			if ($param instanceof Predicate) {
				$output = Hash::merge($output, $this->formatSubPredicate($param));

			} else if ($param instanceof Expr) {
				$output = Hash::merge($output, $this->formatExpression($param));
			}
		}

		return $output;
	}

	/**
	 * Nested predicates need to have individual values wrapped in arrays.
	 *
	 * @param \Titon\Model\Query\Predicate $predicate
	 * @return array
	 */
	public function formatSubPredicate(Predicate $predicate) {
		$output = [];

		foreach ($predicate->getParams() as $param) {
			if ($param instanceof Predicate) {
				$output[] = $this->formatSubPredicate($param);

			} else if ($param instanceof Expr) {
				$output[] = $this->formatExpression($param);
			}
		}

		return [
			$this->getClause($predicate->getType()) => $output
		];
	}

	/**
	 * {@inheritdoc}
	 *
	 * @throws \Titon\Model\Exception\UnsupportedFeatureException
	 */
	public function formatSubQuery(SubQuery $query) {
		throw new UnsupportedFeatureException('MongoDB does not support sub-queries');
	}

	/**
	 * {@inheritdoc}
	 */
	public function formatTable($table, $alias = null) {
		return $table;
	}

	/**
	 * {@inheritdoc}
	 */
	public function formatValues(Query $query) {
		return $query->getFields();
	}

	/**
	 * {@inheritdoc}
	 */
	public function formatWhere(Predicate $where) {
		return $this->formatPredicate($where);
	}

}