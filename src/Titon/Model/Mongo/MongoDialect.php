<?php
/**
 * @copyright	Copyright 2010-2013, The Titon Project
 * @license		http://opensource.org/licenses/bsd-license.php
 * @link		http://titon.io
 */

namespace Titon\Model\Mongo;

use Titon\Model\Driver\Dialect\AbstractDialect;
use Titon\Model\Exception\UnsupportedQueryStatementException;
use Titon\Model\Mongo\Exception\UnsupportedFeatureException;
use Titon\Model\Query;
use Titon\Model\Query\Expr;
use Titon\Model\Query\Predicate;
use Titon\Model\Query\SubQuery;
use \MongoCollection;
use \MongoDB;
use \MongoRegex;

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
	const RENAME = 'rename';
	const SET = 'set';
	const SET_ON_INSERT = 'setOnInsert';
	const SIZE = 'size';
	const TYPE = 'type';
	const REMOVE = 'unset';

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
		self::EXISTS		=> '$exists',
		self::IN			=> '$in',
		self::INC			=> '$inc',
		self::ISOLATED		=> '$isolated',
		self::MAYBE			=> '$or',
		self::NOR			=> '$nor',
		self::NOT			=> '$not',
		self::NOT_IN		=> '$nin',
		self::REGEXP		=> '$regex',
		self::REMOVE		=> '$unset',
		self::RENAME		=> '$rename',
		self::SET			=> '$set',
		self::SET_ON_INSERT => '$setOnInsert',
		self::SIZE			=> '$size',
		self::TYPE			=> '$type',
		self::WHERE			=> '$where',
		'>'		=> '$gt',
		'>='	=> '$gte',
		'<'		=> '$lt',
		'<='	=> '$lte',
		'!='	=> '$ne',
		'%'		=> '$mod',
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
	public function buildCreateIndex(MongoCollection $collection, Query $query) {
		return $collection->ensureIndex($this->formatFields($query), $query->getAttributes() + ['w' => 1]);
	}

	/**
	 * Execute the createCollection() method to create a new collection.
	 *
	 * @param \MongoDB $db
	 * @param \Titon\Model\Query $query
	 * @return array
	 */
	public function buildCreateTable(MongoDB $db, Query $query) {
		$name = $query->getTable();

		if ($schema = $query->getSchema()) {
			$name = $schema->getTable() ?: $name;
		}

		if ($db->createCollection($name, $query->getAttributes())) {
			return ['ok' => 1, 'n' => 1];
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
	public function buildDelete(MongoCollection $collection, Query $query) {
		return $collection->remove($this->formatWhere($query->getWhere()), $query->getAttributes() + ['w' => 1]);
	}

	/**
	 * Execute the deleteIndex() method to delete a collection index.
	 *
	 * @param \MongoCollection $collection
	 * @param \Titon\Model\Query $query
	 * @return array
	 */
	public function buildDropIndex(MongoCollection $collection, Query $query) {
		return $collection->deleteIndex($this->formatFields($query), $query->getAttributes() + ['w' => 1]);
	}

	/**
	 * Execute the drop() method to delete a collection.
	 *
	 * @param \MongoCollection $collection
	 * @param \Titon\Model\Query $query
	 * @return array
	 */
	public function buildDropTable(MongoCollection $collection, Query $query) {
		return $collection->drop();
	}

	/**
	 * Execute the insert() method to create a record. Grab the new record ID from the response.
	 *
	 * @param \MongoCollection $collection
	 * @param \Titon\Model\Query $query
	 * @return array
	 */
	public function buildInsert(MongoCollection $collection, Query $query) {
		$values = $this->formatValues($query);
		$response = $collection->insert($values, $query->getAttributes() + ['w' => 1]);

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
	public function buildMultiInsert(MongoCollection $collection, Query $query) {
		return $collection->batchInsert($this->formatValues($query), $query->getAttributes() + ['w' => 1]);
	}

	/**
	 * Execute the find() method to select records and return a cursor object.
	 *
	 * @param \MongoCollection $collection
	 * @param \Titon\Model\Query $query
	 * @return \MongoCursor
	 */
	public function buildSelect(MongoCollection $collection, Query $query) {
		$cursor = $collection->find($this->formatWhere($query->getWhere()), $this->formatFields($query));

		if ($orderBy = $query->getOrderBy()) {
			$cursor->sort($this->formatOrderBy($orderBy));
		}

		return $cursor;
	}

	/**
	 * Disable sub-queries.
	 *
	 * @param \MongoCollection $collection
	 * @param \Titon\Model\Query\SubQuery $query
	 * @throws \Titon\Model\Exception\UnsupportedQueryStatementException
	 */
	public function buildSubQuery(MongoCollection $collection, SubQuery $query) {
		throw new UnsupportedQueryStatementException('MongoDB does not support sub-queries');
	}

	/**
	 * MongoDB doesn't support truncation, so simply remove all records.
	 *
	 * @param \MongoCollection $collection
	 * @param \Titon\Model\Query $query
	 * @return array
	 */
	public function buildTruncate(MongoCollection $collection, Query $query) {
		return $collection->remove([]);
	}

	/**
	 * Execute the update() method to update a record or multiple records.
	 *
	 * @param \MongoCollection $collection
	 * @param \Titon\Model\Query $query
	 * @return array
	 */
	public function buildUpdate(MongoCollection $collection, Query $query) {
		return $collection->update($this->formatWhere($query->getWhere()), $this->formatFields($query), $query->getAttributes() + ['w' => 1, 'upsert' => true, 'multiple' => true]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function formatExpression(Expr $expr) {
		$operator = $expr->getOperator();
		$value = $expr->getValue();

		switch ($operator) {
			case '=':
				// Do nothing
			break;
			case Expr::BETWEEN:
				$value = [
					$this->getClause('gte') => $value[0],
					$this->getClause('lte') => $value[1]
				];
			break;
			case Expr::NOT_BETWEEN:
				$value = [
					$this->getClause('gte') => $value[1],
					$this->getClause('lte') => $value[0]
				];
			break;
			case Expr::LIKE:
			case Expr::REGEXP:
			case Expr::RLIKE:
				$value = new MongoRegex($value);
			break;
			case Expr::NOT_LIKE:
			case Expr::NOT_REGEXP:
				$value = [$this->getClause(self::NOT) => new MongoRegex($value)];
			break;
			default:
				if (substr($operator, 0, 1) !== '$') {
					$operator = $this->getClause($operator);
				}

				$value = [$operator => $value];
			break;
		}

		return [$expr->getField() => $value];
	}

	/**
	 * {@inheritdoc}
	 */
	public function formatFields(Query $query) {
		switch ($query->getType()) {
			case Query::CREATE_INDEX:
				$fields = [];

				foreach ($query->getFields() as $field => $sort) {
					if (is_numeric($field)) {
						$field = $sort;
						$sort = self::ASC;
					}

					$fields[$field] = $sort;
				}

				return $this->formatOrderBy($fields);
			break;
			case Query::UPDATE:
				$fields = [];
				$set = [];

				foreach ($query->getFields() as $field => $value) {
					if (substr($fields, 0, 1) === '$') {
						$fields[$field] = $value;
					} else {
						$set[$field] = $value;
					}
				}

				if ($set) {
					if (isset($fields['$set'])) {
						$fields['$set'] = array_merge($fields['$set'], $set);
					} else {
						$fields['$set'] = $set;
					}
				}

				return $fields;
			break;
			case Query::SELECT:
				$fields = $query->getFields();

				if ($fields) {
					$fields = array_map(function() {
						return 1;
					}, array_flip($fields));

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
	 *
	 * @throws \Titon\Model\Mongo\Exception\UnsupportedFeatureException
	 */
	public function formatGroupBy(array $groupBy) {
		throw new UnsupportedFeatureException('MongoDB does not record grouping');
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
	 * @throws \Titon\Model\Mongo\Exception\UnsupportedFeatureException
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
			if ($direction === self::ASC) {
				$output[$field] = MongoCollection::ASCENDING;
			} else {
				$output[$field] = MongoCollection::DESCENDING;
			}
		}

		return $output;
	}

	/**
	 * {@inheritdoc}
	 */
	public function formatPredicate(Predicate $predicate) {
		$output = [];

		foreach ($predicate->getParams() as $param) {
			if ($param instanceof Predicate) {
				$output[$this->getClause($param->getType())] = $this->formatPredicate($param);

			} else if ($param instanceof Expr) {
				$output = $this->formatExpression($param) + $output;
			}
		}

		// Or needs to be wrapped in $or
		if ($predicate->getType() === Predicate::EITHER) {
			return [$this->getClause(self::EITHER) => $output];
		}

		return $output;
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