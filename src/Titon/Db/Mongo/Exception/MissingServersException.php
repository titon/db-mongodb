<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Db\Mongo\Exception;

/**
 * Exception thrown when a list of servers is missing for a replicaSet.
 *
 * @package Titon\Db\Mongo\Exception
 */
class MissingServersException extends \OutOfRangeException {

}