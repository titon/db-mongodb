<?php
/**
 * @copyright	Copyright 2010-2013, The Titon Project
 * @license		http://opensource.org/licenses/bsd-license.php
 * @link		http://titon.io
 */

namespace Titon\Model\Mongo;

use Titon\Model\Driver\Schema;
use Titon\Model\Mongo\MongoQuery;
use Titon\Model\Query;
use Titon\Utility\Hash;

/**
 * A model layer specific to MongoDB.
 *
 * @package Titon\Model\Mongo
 */
class Model extends \Titon\Model\Model {

	/**
	 * Configuration.
	 *
	 * @type array
	 */
	protected $_config = [
		'entity' => 'Titon\Model\Mongo\Document'
	];

	/**
	 * {@inheritdoc}
	 */
	public function fetchList(Query $query, $key = null, $value = null, array $options = []) {
		$options['wrap'] = false;
		$results = $this->_processFetch($query, __FUNCTION__, $options);

		$key = $key ?: $this->getPrimaryKey();
		$value = $value ?: $this->getDisplayField();
		$list = [];

		foreach ($results as $result) {

			// Flatten ID objects so that we can extract
			if (isset($result['_id'])) {
				$result['_id'] = (string) $result['_id'];
			}

			$list[Hash::extract($result, $key)] = Hash::extract($result, $value);
		}

		return $list;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPrimaryKey() {
		return '_id'; // Should always be _id
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSchema() {
		if ($this->_schema instanceof Schema) {
			return $this->_schema;
		}

		$this->setSchema(new Schema($this->getTable(), $this->_schema));

		return $this->_schema;
	}

	/**
	 * {@inheritdoc}
	 */
	public function query($type) {
		$this->data = [];

		$query = new MongoQuery($type, $this);
		$query->from($this->getTable(), $this->getAlias());

		return $query;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function _filterData(array &$data) {
		$aliases = array_keys($this->getRelations());
		$related = [];

		foreach ($aliases as $alias) {
			if (isset($data[$alias])) {
				$related[$alias] = $data[$alias];
				unset($data[$alias]);
			}
		}

		return $related;
	}

}