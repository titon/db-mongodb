<?php
/**
 * @copyright	Copyright 2010-2013, The Titon Project
 * @license		http://opensource.org/licenses/bsd-license.php
 * @link		http://titon.io
 */

namespace Titon\Model\Mongo;

use Titon\Test\Stub\Model\Book;
use Titon\Test\Stub\Model\Genre;
use Titon\Test\Stub\Model\Profile;
use Titon\Test\Stub\Model\Series;
use Titon\Test\Stub\Model\User;
use Titon\Test\TestCase;

/**
 * Tests that deal with model relationships.
 */
class RelationTest extends TestCase {

	/**
	 * Unload fixtures.
	 */
	protected function tearDown() {
		parent::tearDown();

		$this->unloadFixtures();
	}

	/**
	 * Test one-to-one relationships.
	 */
	public function testOneToOne() {
		$this->loadFixtures(['Users', 'Profiles']);

		$user = new User();

		// Create
		$user_id = $user->create([
			'username' => 'ironman',
			'firstName' => 'Tony',
			'lastName' => 'Stark',
			'Profile' => [
				'category' => 'Superhero'
			]
		]);
		$profile_id = $user->Profile->id;

		// Read
		$this->assertEquals([
			'_id' => $user_id,
			'username' => 'ironman',
			'firstName' => 'Tony',
			'lastName' => 'Stark',
			'Profile' => [
				'_id' => $profile_id,
				'category' => 'Superhero',
				'user_id' => $user_id
			]
		], $user->select()->where('_id', $user_id)->with('Profile')->fetch(false));

		// Update
		$user->update($user_id, [
			'age' => 38,
			'Profile' => [
				'_id' => $profile_id,
				'status' => 'active'
			]
		]);

		$this->assertEquals([
			'_id' => $user_id,
			'username' => 'ironman',
			'firstName' => 'Tony',
			'lastName' => 'Stark',
			'age' => 38,
			'Profile' => [
				'_id' => $profile_id,
				'category' => 'Superhero',
				'user_id' => $user_id,
				'status' => 'active'
			]
		], $user->select()->where('_id', $user_id)->with('Profile')->fetch(false));

		// Delete w/ cascade
		$this->assertTrue($user->exists($user_id));
		$this->assertTrue($user->Profile->exists($profile_id));

		$user->delete($user_id, true);

		$this->assertFalse($user->exists($user_id));
		$this->assertFalse($user->Profile->exists($profile_id));
	}

	/**
	 * Test one-to-many relationships.
	 */
	public function testOneToMany() {
		$this->loadFixtures(['Books', 'Series']);

		$series = new Series();

		// Create
		$series_id = $series->create([
			'name' => 'A Series Of Unfortunate Events',
			'Books' => [
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
				['name' => 'The Penultimate Peril']
			]
		]);

		// Read
		$actual = $series->select()->where('_id', $series_id)->with('Books')->fetch(false);
		$book1_id = $actual['Books'][0]['_id'];

		$this->assertEquals('A Series Of Unfortunate Events', $actual['name']);
		$this->assertEquals(12, count($actual['Books']));

		// Update
		$series->update($series_id, [
			'author' => 'Lemony Snicket',
			'Books' => [
				['_id' => $book1_id, 'name' => 'The Bad Beginning (Updated)'], // update
				['name' => 'The End'] // create
			]
		]);

		$actual = $series->select()->where('_id', $series_id)->with('Books', function() {
			$this->orderBy('_id', 'asc');
		})->fetch(false);

		$this->assertArrayHasKey('author', $actual);
		$this->assertEquals('The Bad Beginning (Updated)', $actual['Books'][0]['name']);
		$this->assertEquals(13, count($actual['Books']));

		// Delete w/ cascade
		$this->assertTrue($series->exists($series_id));
		$this->assertTrue($series->Books->exists($book1_id));

		$series->delete($series_id, true);

		$this->assertFalse($series->exists($series_id));
		$this->assertFalse($series->Books->exists($book1_id));
	}

	/**
	 * Test many-to-one relationships. Only reading applies to this relation.
	 */
	public function testManyToOne() {
		$this->loadFixtures(['Users', 'Profiles']);

		$user = new User();
		$profile = new Profile();

		// Create the records using the top model
		$user_id = $user->create([
			'username' => 'ironman',
			'firstName' => 'Tony',
			'lastName' => 'Stark',
			'Profile' => [
				'category' => 'Superhero'
			]
		]);
		$profile_id = $user->Profile->id;

		// Read from child model to return parent (belongs to)
		$this->assertEquals([
			'_id' => $profile_id,
			'category' => 'Superhero',
			'user_id' => $user_id,
			'User' => [
				'_id' => $user_id,
				'username' => 'ironman',
				'firstName' => 'Tony',
				'lastName' => 'Stark',
			]
		], $profile->select()->where('_id', $profile_id)->with('User')->fetch(false));
	}

	/**
	 * Test many-to-many relationships. Should really be a nested document...
	 */
	public function testManyToMany() {
		$this->loadFixtures(['Genres', 'Books']);

		$book = new Book();
		$genre = new Genre();
		$genres = $genre->select()->fetchAll(false);
		$g1_id = $genres[0]['_id'];
		$g2_id = $genres[1]['_id'];
		$g3_id = $genres[2]['_id'];

		// Create, Update
		$book_id = $book->create([
			'name' => 'The Winds of Winter',
			'Genres' => [
				['_id' => $g1_id, 'name' => 'Action'], // Existing genre
				['name' => 'Adventure'], // New genre
				['genre_id' => $g3_id] // Existing genre by ID
			]
		]);

		// Read
		$actual = $book->select()->where('_id', $book_id)->with('Genres')->fetch(false);

		$this->assertEquals(3, count($actual['Genres']));

		// Delete w/ cascade
		$this->assertTrue($book->exists($book_id));
		$this->assertTrue($book->Genres->exists($g1_id));

		$book->delete($book_id, true);

		$this->assertFalse($book->exists($g1_id));
		$this->assertTrue($book->Genres->exists($g1_id)); // Related model doesn't get deleted
	}

}