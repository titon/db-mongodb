<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Db\Mongo\Type;

use Titon\Db\Driver\Type\BlobType as LobType;
use \MongoBinData;

/**
 * Represents a binary or a LOB data type.
 *
 * @package Titon\Db\Mongo\Type
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
        return self::BLOB;
    }

    /**
     * {@inheritdoc}
     */
    public function to($value) {
        if (is_resource($value)) {
            $value = stream_get_contents($value);

        } else if ($value instanceof MongoBinData) {
            return $value;
        }

        return new MongoBinData($value, 2);
    }

}