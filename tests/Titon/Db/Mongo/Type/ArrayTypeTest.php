<?php
/**
 * @copyright   2010-2013, The Titon Project
 * @license     http://opensource.org/licenses/bsd-license.php
 * @link        http://titon.io
 */

namespace Titon\Db\Mongo\Type;

use Titon\Test\Stub\DriverStub;
use Titon\Test\TestCase;

/**
 * Test class for Titon\Db\Mongo\Type\ArrayType.
 *
 * @property \Titon\Db\Mongo\Type\ArrayType $object
 */
class ArrayTypeTest extends TestCase {

    /**
     * This method is called before a test is executed.
     */
    protected function setUp() {
        parent::setUp();

        $this->object = new ArrayType(new DriverStub([]));
    }

    /**
     * Test to database conversion.
     */
    public function testTo() {
        $this->assertSame([123], $this->object->to(123));
        $this->assertSame(['abc'], $this->object->to('abc'));
        $this->assertSame([true], $this->object->to(true));
        $this->assertSame([false], $this->object->to(false));
        $this->assertSame([], $this->object->to(null));
        $this->assertSame(['foo' => 'bar'], $this->object->to(['foo' => 'bar']));
    }

    /**
     * Test name string.
     */
    public function testGetName() {
        $this->assertEquals('array', $this->object->getName());
    }

    /**
     * Test schema options.
     */
    public function testGetDefaultOptions() {
        $this->assertEquals([], $this->object->getDefaultOptions());
    }

}