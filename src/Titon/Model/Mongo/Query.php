<?php
/**
 * @copyright	Copyright 2010-2013, The Titon Project
 * @license		http://opensource.org/licenses/bsd-license.php
 * @link		http://titon.io
 */

namespace Titon\Model\Mongo;

/**
 * A query builder specific to MongoDB.
 *
 * @package Titon\Model\Mongo
 */
class Query extends \Titon\Model\Query {

	/**
	 * Used to track count() queries.
	 *
	 * @type boolean
	 */
	public $isCount;

	/**
	 * {@inheritdoc}
	 */
	public function count() {
		$this->isCount = true;

		return $this->getModel()->count($this);
	}

	/**
	 * Alias for offset().
	 *
	 * @param int $offset
	 * @return \Titon\Model\Query
	 */
	public function skip($offset) {
		return $this->offset($offset);
	}

}