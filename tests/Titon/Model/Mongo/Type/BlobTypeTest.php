<?php
/**
 * @copyright	Copyright 2010-2013, The Titon Project
 * @license		http://opensource.org/licenses/bsd-license.php
 * @link		http://titon.io
 */

namespace Titon\Model\Mongo\Type;

use MongoBinData;
use Titon\Test\Stub\DriverStub;
use Titon\Test\TestCase;

/**
 * Test class for Titon\Model\Mongo\Type\BlobType.
 *
 * @property \Titon\Model\Mongo\Type\BlobType $object
 */
class BlobTypeTest extends TestCase {

	/**
	 * This method is called before a test is executed.
	 */
	protected function setUp() {
		parent::setUp();

		$this->object = new BlobType(new DriverStub('default', []));
	}

	/**
	 * Test to database conversion.
	 */
	public function testTo() {
		$this->assertEquals(new MongoBinData('Raw string', 2), $this->object->to('Raw string'));
		$this->assertEquals(new MongoBinData('This is loading from a file handle', 2), $this->object->to(fopen(TEMP_DIR . '/blob.txt', 'r')));
		$this->assertEquals(new MongoBinData('Raw string', 2), $this->object->to(new MongoBinData('Raw string', 2)));
	}

	/**
	 * Test name string.
	 */
	public function testGetName() {
		$this->assertEquals('blob', $this->object->getName());
	}

	/**
	 * Test schema options.
	 */
	public function testGetDefaultOptions() {
		$this->assertEquals([], $this->object->getDefaultOptions());
	}

}