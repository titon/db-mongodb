<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Db\Mongo\Type;

use Titon\Test\Stub\DriverStub;
use Titon\Test\TestCase;
use \MongoInt64;

/**
 * Test class for Titon\Db\Mongo\Type\Int64Type.
 *
 * @property \Titon\Db\Mongo\Type\Int64Type $object
 */
class Int64TypeTest extends TestCase {

    /**
     * This method is called before a test is executed.
     */
    protected function setUp() {
        parent::setUp();

        $this->object = new Int64Type(new DriverStub([]));
    }

    /**
     * Test to database conversion.
     */
    public function testTo() {
        $this->assertEquals(new MongoInt64('1664523.5'), $this->object->to(1664523.5));
        $this->assertEquals(new MongoInt64('4563453453455'), $this->object->to(4563453453455));
        $this->assertEquals(new MongoInt64('6664567345634563456354'), $this->object->to('6664567345634563456354'));
        $this->assertEquals(new MongoInt64('345634865578969069011341644123'), $this->object->to(new MongoInt64('345634865578969069011341644123')));
    }

    /**
     * Test name string.
     */
    public function testGetName() {
        $this->assertEquals('int64', $this->object->getName());
    }

    /**
     * Test schema options.
     */
    public function testGetDefaultOptions() {
        $this->assertEquals([], $this->object->getDefaultOptions());
    }

}