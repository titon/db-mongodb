<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Db\Mongo;

use Titon\Db\Query;

/**
 * A query builder specific to MongoDB.
 *
 * @package Titon\Db\Mongo
 */
class MongoQuery extends Query {

    /**
     * Used to track count() queries.
     * Doesn't do anything useful.
     *
     * @type boolean
     */
    public $isCount;

    /**
     * {@inheritdoc}
     */
    public function count() {
        $this->isCount = true;

        return $this->getTable()->count($this);
    }

    /**
     * Alias for offset().
     *
     * @param int $offset
     * @return \Titon\Db\Query
     */
    public function skip($offset) {
        return $this->offset($offset);
    }

}