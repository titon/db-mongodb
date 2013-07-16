<?php
/**
 * @copyright	Copyright 2010-2013, The Titon Project
 * @license		http://opensource.org/licenses/bsd-license.php
 * @link		http://titon.io
 */

namespace Titon\Model\Mongo\Type;

use Titon\Model\Driver\Type\BlobType as LobType;
use \MongoBinData;

/**
 * Represents a binary or a LOB data type.
 *
 * @package Titon\Model\Mongo\Type
 */
class BlobType extends LobType {

	/**
	 * {@inheritdoc}
	 */
	public function from($value) {
		return $value;
	}

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
		if (is_resource($value)) {
			$value = stream_get_contents($value);
		}

		return new MongoBinData($value);
	}

}