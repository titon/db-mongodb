<?php
/**
 * @copyright	Copyright 2010-2013, The Titon Project
 * @license		http://opensource.org/licenses/bsd-license.php
 * @link		http://titon.io
 */

namespace Titon\Model\Mongo\Type;

use Titon\Model\Driver\Type\AbstractType;

/**
 * Represents an array data type.
 *
 * @package Titon\Model\Mongo\Type
 */
class ArrayType extends AbstractType {

	/**
	 * {@inheritdoc}
	 */
	public function getName() {
		return 'array';
	}

	/**
	 * {@inheritdoc}
	 */
	public function to($value) {
		return (array) $value;
	}

}