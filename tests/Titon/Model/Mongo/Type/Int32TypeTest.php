<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Model\Mongo\Type;

use MongoInt32;
use Titon\Test\Stub\DriverStub;
use Titon\Test\TestCase;

/**
 * Test class for Titon\Model\Mongo\Type\Int32Type.
 *
 * @property \Titon\Model\Mongo\Type\Int32Type $object
 */
class Int32TypeTest extends TestCase {

    /**
     * This method is called before a test is executed.
     */
    protected function setUp() {
        parent::setUp();

        $this->object = new Int32Type(new DriverStub('default', []));
    }

    /**
     * Test to database conversion.
     */
    public function testTo() {
        $this->assertEquals(new MongoInt32('1664523.5'), $this->object->to(1664523.5));
        $this->assertEquals(new MongoInt32('4563453453455'), $this->object->to(4563453453455));
        $this->assertEquals(new MongoInt32('6664567345634563456354'), $this->object->to('6664567345634563456354'));
        $this->assertEquals(new MongoInt32('345634865578969069011341324123'), $this->object->to(new MongoInt32('345634865578969069011341324123')));
    }

    /**
     * Test name string.
     */
    public function testGetName() {
        $this->assertEquals('int32', $this->object->getName());
    }

    /**
     * Test schema options.
     */
    public function testGetDefaultOptions() {
        $this->assertEquals([], $this->object->getDefaultOptions());
    }

}