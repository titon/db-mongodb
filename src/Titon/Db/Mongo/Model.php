<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Db\Mongo;

use \Titon\Model\Model as BaseModel;

/**
 * A MongoDB specific model.
 *
 * @package Titon\Db\Mongo
 */
class Model extends BaseModel {

    /**
     * Always use _id.
     *
     * @type string
     */
    protected $primaryKey = '_id';

    /**
     * Use document specific entity.
     *
     * @type string
     */
    protected $entity = 'Titon\Db\Mongo\Document';

}