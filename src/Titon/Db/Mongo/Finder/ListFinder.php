<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Db\Mongo\Finder;

use Titon\Db\Driver\Finder\ListFinder as BaseListFinder;

/**
 * Flatten _id fields so they can be extracted.
 *
 * @package Titon\Db\Mongo\Finder
 */
class ListFinder extends BaseListFinder {

    /**
     * {@inheritdoc}
     */
    public function after(array $results, array $options = []) {
        foreach ($results as $result) {
            if (isset($result->_id)) {
                $result->_id = (string) $result->_id;
            }
        }

        return parent::after($results, $options);
    }

}