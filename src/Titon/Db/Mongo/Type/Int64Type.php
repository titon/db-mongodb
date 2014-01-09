<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Db\Mongo\Type;

use \MongoInt64;

/**
 * Represents a 64 bit integer data type.
 *
 * @package Titon\Db\Mongo\Type
 */
class Int64Type extends Int32Type {

    /**
     * {@inheritdoc}
     */
    public function getName() {
        return self::INT . 64;
    }

    /**
     * {@inheritdoc}
     */
    public function to($value) {
        return new MongoInt64((string) $value);
    }

}