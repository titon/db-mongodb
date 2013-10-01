<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model\Mongo\Type;

use Titon\Model\Driver\Type\DateType;
use Titon\Utility\Time;
use \MongoDate;

/**
 * Represents an timestamp data type.
 *
 * @package Titon\Model\Mongo\Type
 */
class DatetimeType extends DateType {

    /**
     * {@inheritdoc}
     */
    public function getDefaultOptions() {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getName() {
        return self::DATETIME;
    }

    /**
     * {@inheritdoc}
     */
    public function to($value) {
        if ($value instanceof MongoDate) {
            return $value;
        }

        return new MongoDate(Time::toUnix($value));
    }

}