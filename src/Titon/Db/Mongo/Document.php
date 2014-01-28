<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Db\Mongo;

use Titon\Model\Model;

/**
 * A MongoDB specific model.
 *
 * @package Titon\Db\Mongo
 */
class Document extends Model {

    /**
     * Always use _id.
     *
     * @type string
     */
    protected $primaryKey = '_id';

    /**
     * {@inheritdoc}
     */
    public function getRepository() {
        if (!$this->_repository) {
            $this->setRepository(new Collection([
                'connection' => $this->connection,
                'table' => $this->table,
                'prefix' => $this->prefix,
                'primaryKey' => $this->primaryKey,
                'displayField' => $this->displayField,
                'entity' => get_class($this)
            ]));
        }

        return $this->_repository;
    }

}