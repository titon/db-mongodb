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
	 * {@inheritdoc}
	 */
	public function count() {
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