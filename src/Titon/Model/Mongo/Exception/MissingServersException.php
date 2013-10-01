<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model\Mongo\Exception;

/**
 * Exception thrown when a list of servers is missing for a replicaSet.
 *
 * @package Titon\Model\Mongo\Exception
 */
class MissingServersException extends \OutOfRangeException {

}