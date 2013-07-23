<?php
/**
 * @copyright	Copyright 2010-2013, The Titon Project
 * @license		http://opensource.org/licenses/bsd-license.php
 * @link		http://titon.io
 */

namespace Titon\Model\Mongo\Type;

use DateTime;
use MongoDate;
use Titon\Test\Stub\DriverStub;
use Titon\Test\TestCase;

/**
 * Test class for Titon\Model\Mongo\Type\DatetimeType.
 *
 * @property \Titon\Model\Mongo\Type\DatetimeType $object
 */
class DatetimeTypeTest extends TestCase {

	/**
	 * This method is called before a test is executed.
	 */
	protected function setUp() {
		parent::setUp();

		$this->object = new DatetimeType(new DriverStub('default', []));
	}

	/**
	 * Test to database conversion.
	 */
	public function testTo() {
		$this->assertEquals(new MongoDate(mktime(0, 2, 5, 2, 26, 1988)), $this->object->to(mktime(0, 2, 5, 2, 26, 1988)));
		$this->assertEquals(new MongoDate(strtotime('2011-03-11 21:05:29')), $this->object->to('2011-03-11 21:05:29'));
		$this->assertEquals(new MongoDate(strtotime('June 6th 1985, 12:33pm')), $this->object->to('June 6th 1985, 12:33pm'));
		$this->assertEquals(new MongoDate(strtotime('1995-11-30 02:44:55')), $this->object->to(new DateTime('1995-11-30 02:44:55')));
		$this->assertEquals(new MongoDate(time()), $this->object->to(new MongoDate(time())));
	}

	/**
	 * Test name string.
	 */
	public function testGetName() {
		$this->assertEquals('datetime', $this->object->getName());
	}

	/**
	 * Test schema options.
	 */
	public function testGetDefaultOptions() {
		$this->assertEquals([], $this->object->getDefaultOptions());
	}

}