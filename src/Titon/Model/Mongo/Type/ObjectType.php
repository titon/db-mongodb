<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model\Mongo\Type;

/**
 * Represents an object (associative array) data type.
 *
 * @package Titon\Model\Mongo\Type
 */
class ObjectType extends ArrayType {

    /**
     * {@inheritdoc}
     */
    public function getName() {
        return 'object';
    }

}