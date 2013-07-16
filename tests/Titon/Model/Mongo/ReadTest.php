<?php
/**
 * @copyright	Copyright 2010-2013, The Titon Project
 * @license		http://opensource.org/licenses/bsd-license.php
 * @link		http://titon.io
 */

namespace Titon\Model\Mongo;

use Exception;
use Titon\Model\Entity;
use Titon\Test\Stub\Model\Book;
use Titon\Test\Stub\Model\Genre;
use Titon\Test\Stub\Model\Stat;
use Titon\Test\Stub\Model\User;
use Titon\Test\TestCase;

/**
 * Test class for database reading.
 */
class ReadTest extends TestCase {

	/**
	 * Unload fixtures.
	 */
	protected function tearDown() {
		parent::tearDown();

		$this->unloadFixtures();
	}

	/**
	 * Test basic fetching of rows.
	 */
	public function testFetch() {
		$this->loadFixtures('Books');

		$book = new Book();

		// Single
		$this->assertEquals([
			'series_id' => 1,
			'name' => 'A Game of Thrones',
			'isbn' => '0-553-10354-7',
			'released' => '1996-08-02'
		], $this->removeIDs($book->select()->fetch(false)));

		// Multiple
		$this->assertEquals([
			[
				'series_id' => 3,
				'name' => 'The Fellowship of the Ring',
				'isbn' => '',
				'released' => '1954-07-24'
			],
			[
				'series_id' => 3,
				'name' => 'The Two Towers',
				'isbn' => '',
				'released' => '1954-11-11'
			],
			[
				'series_id' => 3,
				'name' => 'The Return of the King',
				'isbn' => '',
				'released' => '1955-10-25'
			],
		], $this->removeIDs($book->select()->where('series_id', 3)->orderBy('id', 'asc')->fetchAll(false)));
	}

	/**
	 * Test row counting.
	 */
	public function testSelectCount() {
		$this->loadFixtures('Books');

		$book = new Book();

		$query = $book->select();
		$this->assertEquals(15, $query->count());

		$query->where('series_id', 2);
		$this->assertEquals(7, $query->count());

		$query->where('name', 'like', '/prince/i');
		$this->assertEquals(1, $query->count());
	}

	/**
	 * Test LIKE and NOT LIKE clauses.
	 */
	public function testSelectLike() {
		$this->loadFixtures('Users');

		$user = new User();

		$this->assertEquals([
			['username' => 'batman'],
			['username' => 'superman'],
			['username' => 'spiderman'],
		], $user->select('username')->where('username', 'like', '/man/')->orderBy('_id', 'asc')->fetchAll(false));

		$this->assertEquals([
			['username' => 'miles'],
			['username' => 'wolverine']
		], $user->select('username')->where('username', 'notLike', '/man/')->orderBy('_id', 'asc')->fetchAll(false));
	}

	/**
	 * Test REGEXP and NOT REGEXP clauses.
	 */
	public function testSelectRegexp() {
		$this->loadFixtures('Users');

		$user = new User();

		$this->assertEquals([
			['username' => 'batman'],
			['username' => 'superman'],
			['username' => 'spiderman'],
		], $user->select('username')->where('username', 'regexp', '/man$/')->orderBy('id', 'asc')->fetchAll(false));

		$this->assertEquals([
			['username' => 'miles'],
			['username' => 'wolverine']
		], $user->select('username')->where('username', 'notRegexp', '/man$/')->fetchAll(false));
	}

	/**
	 * Test IN and NOT IN clauses.
	 */
	public function testSelectIn() {
		$this->loadFixtures('Users');

		$user = new User();

		$this->assertEquals([
			['username' => 'miles'],
			['username' => 'superman'],
		], $user->select('username')->where('username', 'in', ['miles', 'superman'])->fetchAll(false)); // use fake 10

		$this->assertEquals([
			['username' => 'batman'],
			['username' => 'spiderman'],
			['username' => 'wolverine']
		], $user->select('username')->where('username', 'notIn', ['miles', 'superman'])->fetchAll(false));
	}

	/**
	 * Test BETWEEN and NOT BETWEEN clauses.
	 */
	public function testSelectBetween() {
		$this->loadFixtures('Users');

		$user = new User();

		$this->assertEquals([
			['username' => 'batman'],
			['username' => 'superman'],
		], $user->select('username')->where('age', 'between', [30, 45])->fetchAll(false));

		$this->assertEquals([
			['username' => 'miles'],
			['username' => 'spiderman'],
			['username' => 'wolverine']
		], $user->select('username')->where('age', 'notBetween', [30, 45])->fetchAll(false));
	}

	/**
	 * Test IS NULL and NOT NULL clauses.
	 */
	public function testSelectNull() {
		$this->loadFixtures('Users');

		$user = new User();
		$user->query(Query::UPDATE)->fields(['created' => null])->where('country_id', 1)->save();

		$this->assertEquals([
			['username' => 'miles']
		], $user->select('username')->where('created', 'isNull', null)->fetchAll(false));

		$this->assertEquals([
			['username' => 'batman'],
			['username' => 'superman'],
			['username' => 'spiderman'],
			['username' => 'wolverine']
		], $user->select('username')->where('created', 'isNotNull', null)->orderBy('_id', 'asc')->fetchAll(false));
	}

	/**
	 * Test field filtering.
	 */
	public function testFieldFiltering() {
		$this->loadFixtures('Books');

		$book = new Book();

		$this->assertEquals([
			new Entity(['name' => 'A Game of Thrones']),
			new Entity(['name' => 'A Clash of Kings']),
			new Entity(['name' => 'A Storm of Swords']),
			new Entity(['name' => 'A Feast for Crows']),
			new Entity(['name' => 'A Dance with Dragons']),
		], $book->select('name')->where('series_id', 1)->orderBy('_id', 'asc')->fetchAll());
	}

	/**
	 * Test group by clause.
	 */
	public function testGrouping() {
		$this->loadFixtures('Books');

		$book = new Book();

		$this->assertEquals(3, count($book->select('name')->groupBy('series_id')->orderBy('id', 'asc')->fetchAll(false)));
	}

	/**
	 * Test limit and offset.
	 */
	public function testLimiting() {
		$this->loadFixtures('Genres');

		$genre = new Genre();

		// Limit only
		$this->assertEquals([
			new Entity(['name' => 'Action']),
			new Entity(['name' => 'Adventure']),
			new Entity(['name' => 'Action-Adventure'])
		], $genre->select('name')->limit(3)->fetchAll());

		// Limit and offset
		$this->assertEquals([
			new Entity(['name' => 'Comedy']),
			new Entity(['name' => 'Horror']),
			new Entity(['name' => 'Thriller'])
		], $genre->select('name')->limit(3, 3)->fetchAll());
	}

	/**
	 * Test order by clause.
	 */
	public function testOrdering() {
		$this->loadFixtures('Books');

		$book = new Book();

		$this->assertEquals([
			new Entity(['series_id' => 3, 'name' => 'The Return of the King']),
			new Entity(['series_id' => 3, 'name' => 'The Two Towers']),
			new Entity(['series_id' => 3, 'name' => 'The Fellowship of the Ring']),
			new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Deathly Hallows']),
			new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Half-blood Prince']),
			new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Order of the Phoenix']),
			new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Goblet of Fire']),
			new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Prisoner of Azkaban']),
			new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Chamber of Secrets']),
			new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Philosopher\'s Stone']),
			new Entity(['series_id' => 1, 'name' => 'A Dance with Dragons']),
			new Entity(['series_id' => 1, 'name' => 'A Feast for Crows']),
			new Entity(['series_id' => 1, 'name' => 'A Storm of Swords']),
			new Entity(['series_id' => 1, 'name' => 'A Clash of Kings']),
			new Entity(['series_id' => 1, 'name' => 'A Game of Thrones']),
		], $book->select('series_id', 'name')->orderBy([
			'series_id' => 'desc',
			'_id' => 'desc'
		])->fetchAll());

		$this->assertEquals([
			new Entity(['series_id' => 3, 'name' => 'The Fellowship of the Ring']),
			new Entity(['series_id' => 3, 'name' => 'The Return of the King']),
			new Entity(['series_id' => 3, 'name' => 'The Two Towers']),
			new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Chamber of Secrets']),
			new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Deathly Hallows']),
			new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Goblet of Fire']),
			new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Half-blood Prince']),
			new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Order of the Phoenix']),
			new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Philosopher\'s Stone']),
			new Entity(['series_id' => 2, 'name' => 'Harry Potter and the Prisoner of Azkaban']),
			new Entity(['series_id' => 1, 'name' => 'A Clash of Kings']),
			new Entity(['series_id' => 1, 'name' => 'A Dance with Dragons']),
			new Entity(['series_id' => 1, 'name' => 'A Feast for Crows']),
			new Entity(['series_id' => 1, 'name' => 'A Game of Thrones']),
			new Entity(['series_id' => 1, 'name' => 'A Storm of Swords']),
		], $book->select('series_id', 'name')->orderBy([
			'series_id' => 'desc',
			'name' => 'asc'
		])->fetchAll());
	}

	/**
	 * Test where predicates using AND conjunction.
	 */
	public function testWhereAnd() {
		$this->loadFixtures('Stats');

		$stat = new Stat();

		$this->assertEquals([
			[
				'name' => 'Ranger',
				'health' => 800,
				'isMelee' => false
			]
		], $stat->select('name', 'health', 'isMelee')
			->where('isMelee', false)
			->where('health', '>=', 700)
			->fetchAll(false));

		$this->assertEquals([
			[
				'name' => 'Ranger',
				'health' => 800,
				'energy' => 335,
				'range' => 6.75
			], [
				'name' => 'Mage',
				'health' => 600,
				'energy' => 600,
				'range' => 8.33
			]
		], $stat->select('name', 'health', 'energy', 'range')
			->where('health', '<', 1000)
			->where('range', '>=', 5)
			->where('energy', '!=', 0)
			->fetchAll(false));

		$this->assertEquals([
			[
				'name' => 'Warrior',
				'health' => 1500,
				'isMelee' => true,
				'range' => 1
			]
		], $stat->select('name', 'health', 'isMelee', 'range')
			->where(function() {
				$this->gte('health', 500)->lte('range', 7)->eq('isMelee', true);
			})->fetchAll(false));
	}

	/**
	 * Test where predicates using OR conjunction.
	 */
	public function testWhereOr() {
		$this->loadFixtures('Stats');

		$stat = new Stat();

		$this->assertEquals([
			[
				'name' => 'Warrior',
				'health' => 1500,
				'range' => 1
			], [
				'name' => 'Mage',
				'health' => 600,
				'range' => 8.33
			]
		], $stat->select('name', 'health', 'range')
			->orWhere('health', '>', 1000)
			->orWhere('range', '>', 7)
			->fetchAll(false));

		$this->assertEquals([
			[
				'name' => 'Warrior',
				'damage' => 125.25,
				'defense' => 55.75,
				'range' => 1
			], [
				'name' => 'Ranger',
				'damage' => 90.45,
				'defense' => 30.5,
				'range' => 6.75
			], [
				'name' => 'Mage',
				'damage' => 55.84,
				'defense' => 40.15,
				'range' => 8.33
			]
		], $stat->select('name', 'damage', 'defense', 'range')
			->orWhere(function() {
				$this->gt('damage', 100)->gt('range', 5)->gt('defense', 50);
			})
			->fetchAll(false));
	}

	/**
	 * Test nested where predicates.
	 */
	public function testWhereNested() {
		$this->loadFixtures('Stats');

		$stat = new Stat();

		$this->assertEquals([
			['name' => 'Mage']
		], $stat->select('name')
			->where(function() {
				$this->eq('isMelee', false);
				$this->either(function() {
					$this->lte('health', 600)->lte('damage', 60);
				});
			})->fetchAll(false));
	}

	/**
	 * Remove _id from results since we cant match against it.
	 *
	 * @param array $data
	 * @return array
	 */
	protected function removeIDs($data) {
		$isSingle = false;

		if (!isset($data[0])) {
			$data = array($data);
			$isSingle = true;
		}

		foreach ($data as &$row) {
			unset($row['_id']);
		}

		if ($isSingle) {
			return $data[0];
		}

		return $data;
	}

}