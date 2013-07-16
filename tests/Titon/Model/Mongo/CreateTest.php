<?php
/**
 * @copyright	Copyright 2010-2013, The Titon Project
 * @license		http://opensource.org/licenses/bsd-license.php
 * @link		http://titon.io
 */

namespace Titon\Model\Mongo;

use Titon\Model\Query;
use Titon\Test\Stub\Model\Series;
use Titon\Test\Stub\Model\User;
use Titon\Test\TestCase;
use \Exception;

/**
 * Test class for database inserting.
 */
class CreateTest extends TestCase {

	/**
	 * Unload fixtures.
	 */
	protected function tearDown() {
		parent::tearDown();

		$this->unloadFixtures();
	}

	/**
	 * Test basic row inserting. Response should be the new ID.
	 */
	public function testCreate() {
		$this->loadFixtures('Users');

		$user = new User();
		$data = [
			'country_id' => 1,
			'username' => 'ironman',
			'firstName' => 'Tony',
			'lastName' => 'Stark',
			'password' => '7NAks9193KAkjs1',
			'email' => 'ironman@email.com',
			'age' => 38
		];

		$last_id = $user->create($data);
		$this->assertInstanceOf('MongoId', $last_id);

		$this->assertEquals([
			'_id' => $last_id,
			'country_id' => 1,
			'username' => 'ironman',
			'firstName' => 'Tony',
			'lastName' => 'Stark',
			'password' => '7NAks9193KAkjs1',
			'email' => 'ironman@email.com',
			'age' => 38
		], $user->data);
	}

	/**
	 * Test row inserting with one to one relation data.
	 */
	public function testCreateWithOneToOne() {
		$this->loadFixtures(['Users', 'Profiles']);

		$user = new User();
		$data = [
			'country_id' => 1,
			'username' => 'ironman',
			'firstName' => 'Tony',
			'lastName' => 'Stark',
			'password' => '7NAks9193KAkjs1',
			'email' => 'ironman@email.com',
			'age' => 38,
			'Profile' => [
				'lastLogin' => '2012-06-24 17:30:33'
			]
		];

		$last_id = $user->create($data);
		$this->assertInstanceOf('MongoId', $last_id);

		$this->assertArraysEqual([
			'_id' => $last_id,
			'country_id' => 1,
			'username' => 'ironman',
			'firstName' => 'Tony',
			'lastName' => 'Stark',
			'password' => '7NAks9193KAkjs1',
			'email' => 'ironman@email.com',
			'age' => 38,
			'Profile' => [
				'_id' => $user->Profile->id,
				'lastLogin' => '2012-06-24 17:30:33',
				'user_id' => $last_id
			]
		], $user->data, true);

		// Should throw errors for invalid array structure
		unset($data['id'], $data['Profile']);

		$data['Profile'] = [
			['lastLogin' => '2012-06-24 17:30:33'] // Nested array
		];

		try {
			$user->create($data);
			$this->assertTrue(false);
		} catch (Exception $e) {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test that create fails with empty data.
	 */
	public function testCreateEmptyData() {
		$this->loadFixtures('Users');

		$user = new User();

		$this->assertSame(0, $user->create([]));

		// Relation without data
		try {
			$this->assertSame(0, $user->create([
				'Profile' => [
					'lastLogin' => time()
				]
			]));
			$this->assertTrue(false);
		} catch (Exception $e) {
			$this->assertTrue(true);
		}
	}


	/**
	 * Test row inserting with one to many relation data.
	 */
	public function testCreateWithOneToMany() {
		$this->loadFixtures(['Series', 'Books']);

		$series = new Series();
		$books = [
			['name' => 'The Bad Beginning'],
			['name' => 'The Reptile Room'],
			['name' => 'The Wide Window'],
			['name' => 'The Miserable Mill'],
			['name' => 'The Austere Academy'],
			['name' => 'The Ersatz Elevator'],
			['name' => 'The Vile Village'],
			['name' => 'The Hostile Hospital'],
			['name' => 'The Carnivorous Carnival'],
			['name' => 'The Slippery Slope'],
			['name' => 'The Grim Grotto'],
			['name' => 'The Penultimate Peril'],
			['name' => 'The End'],
		];

		$data = [
			'name' => 'A Series Of Unfortunate Events',
			'Books' => $books
		];

		$last_id = $series->create($data);
		$this->assertInstanceOf('MongoId', $last_id);

		// Remove IDs since we cant match it
		$actual = $series->data;

		foreach ($actual['Books'] as &$row) {
			unset($row['_id']);
		}

		$this->assertArraysEqual([
			'_id' => $last_id,
			'name' => 'A Series Of Unfortunate Events',
			'Books' => [
				['name' => 'The Bad Beginning', 'series_id' => $last_id],
				['name' => 'The Reptile Room', 'series_id' => $last_id],
				['name' => 'The Wide Window', 'series_id' => $last_id],
				['name' => 'The Miserable Mill', 'series_id' => $last_id],
				['name' => 'The Austere Academy', 'series_id' => $last_id],
				['name' => 'The Ersatz Elevator', 'series_id' => $last_id],
				['name' => 'The Vile Village', 'series_id' => $last_id],
				['name' => 'The Hostile Hospital', 'series_id' => $last_id],
				['name' => 'The Carnivorous Carnival', 'series_id' => $last_id],
				['name' => 'The Slippery Slope', 'series_id' => $last_id],
				['name' => 'The Grim Grotto', 'series_id' => $last_id],
				['name' => 'The Penultimate Peril', 'series_id' => $last_id],
				['name' => 'The End', 'series_id' => $last_id],
			]
		], $actual, true);

		// Should throw errors for invalid array structure
		unset($data['id'], $data['Books']);

		$data['Books'] = [
			'name' => 'The Bad Beginning'
		]; // Non numeric array

		try {
			$this->assertEquals(4, $series->create($data));
			$this->assertTrue(false);
		} catch (Exception $e) {
			$this->assertTrue(true);
		}
	}

	/**
	 * Test inserting multiple records with a single statement.
	 */
	public function testCreateMany() {
		// Dont load fixtures

		$user = new User();
		$user->createTable();

		$this->assertEquals(0, $user->select()->count());

		$this->assertEquals(5, $user->createMany([
			['country_id' => 1, 'username' => 'miles', 'firstName' => 'Miles', 'lastName' => 'Johnson', 'password' => '1Z5895jf72yL77h', 'email' => 'miles@email.com', 'age' => 25, 'created' => '1988-02-26 21:22:34'],
			['country_id' => 3, 'username' => 'batman', 'firstName' => 'Bruce', 'lastName' => 'Wayne', 'created' => '1960-05-11 21:22:34'],
			['country_id' => 2, 'username' => 'superman', 'email' => 'superman@email.com', 'age' => 33, 'created' => '1970-09-18 21:22:34'],
			['country_id' => 5, 'username' => 'spiderman', 'firstName' => 'Peter', 'lastName' => 'Parker', 'password' => '1Z5895jf72yL77h', 'email' => 'spiderman@email.com', 'age' => 22, 'created' => '1990-01-05 21:22:34'],
			['country_id' => 4, 'username' => 'wolverine', 'password' => '1Z5895jf72yL77h', 'email' => 'wolverine@email.com'],
		]));

		$this->assertEquals(5, $user->select()->count());

		$user->query(Query::DROP_TABLE)->save();
	}

	/**
	 * Test inserts with arrays and objects.
	 */
	public function testCreateWithNestedData() {
		$this->loadFixtures('Users');

		$user = new User();
		$data = [
			'string' => 'miles',
			'boolean' => true,
			'integer' => 123456,
			'null' => null,
			'array' => [1, 2, 3, 4, 5],
			'object' => [
				'foo' => 'bar'
			],
			'datetime' => new \MongoDate()
		];

		$last_id = $user->create($data);
		$this->assertInstanceOf('MongoId', $last_id);

		$this->assertEquals([
			'_id' => $last_id,
			'string' => 'miles',
			'boolean' => true,
			'integer' => 123456,
			'null' => null,
			'array' => [1, 2, 3, 4, 5],
			'object' => [
				'foo' => 'bar'
			],
			'datetime' => new \MongoDate()
		], $user->data);
	}

}