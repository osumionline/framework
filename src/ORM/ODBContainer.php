<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\ORM;

use PDO;

/**
 * ODBContainer - Class to store all the opened connections to the databases and methods to create new connections or close existing ones
 */
class ODBContainer {
	private array $connections = [];

	/**
	 * Get a previously established connection or create a new one and store it.
	 *
	 * @param string $driver Driver used to connect to the database (eg mysql, postgresql, sqlite...).
	 * @param string $host Host name where the database is.
	 * @param string $user Username to connect to the database.
	 * @param string $pass Password to connect to the database.
	 * @param string $name Name of the database to connect to.
	 * @param string $charset Charset used in the database connection.
	 *
	 * @return array Connection data, containing the connection index and PDO link.
	 */
	public function getConnection(
		string $driver,
		string $host,
		string $user,
		string $pass,
		string $name,
		string $charset
	): array {
		$index = $this->getConnectionIndex(
			$driver,
			$host,
			$user,
			$pass,
			$name,
			$charset
		);

		if (!array_key_exists($index, $this->connections)) {
			$conn = new PDO(
				$driver . ':host=' . $host . ';dbname=' . $name . ';charset=' . $charset,
				$user,
				$pass,
				[
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_EMULATE_PREPARES => false,
					PDO::ATTR_STRINGIFY_FETCHES => false
				]
			);

			$this->connections[$index] = $conn;
		}

		return [
			'index' => $index,
			'link'  => $this->connections[$index]
		];
	}

	/**
	 * Generate an opaque and deterministic index for a database connection.
	 *
	 * @param string $driver Driver used to connect to the database.
	 * @param string $host Host name where the database is located.
	 * @param string $user Username used to connect to the database.
	 * @param string $pass Password used to connect to the database.
	 * @param string $name Database name.
	 * @param string $charset Database connection charset.
	 *
	 * @return string Hashed connection index.
	 */
	private function getConnectionIndex(
		string $driver,
		string $host,
		string $user,
		string $pass,
		string $name,
		string $charset
	): string {
		$values = [
			$driver,
			$host,
			$user,
			$pass,
			$name,
			$charset
		];

		$index_source = '';

		foreach ($values as $value) {
			$index_source .= strlen($value) . ':' . $value . ';';
		}

		return hash('sha256', $index_source);
	}

	/**
	 * Close a connection to the database
	 *
	 * @param string $index Hashed index of the connection
	 *
	 * @return bool Returns connection was closed or not
	 */
	public function closeConnection(string $index): bool {
		if (array_key_exists($index, $this->connections)) {
			$this->connections[$index] = null;
			unset($this->connections[$index]);
			return true;
		}

		return false;
	}

	/**
	 * Close all stored connections to the databases
	 *
	 * @return void
	 */
	public function closeAllConnections(): void {
		foreach ($this->connections as $index => $link) {
			$this->connections[$index] = null;
			unset($this->connections[$index]);
		}
	}
}
