<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\ORM;

use PDO;
use PDOException;
use Exception;
use PDOStatement;

class ODB {
    private string $driver  = 'mysql';
    private ?string $host    = null;
    private ?string $user    = null;
    private ?string $pass    = null;
    private ?string $name    = null;
    private ?string $charset = 'UTF8';
    private ?string $connection_index = null;
    public ?PDO $link = null;
    private ?PDOStatement $stmt = null;
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $result = [];
    private int $result_index = 0;

    /**
     * ODB constructor. If no parameters are present, gets connection data from $core
     *
     * @param string $user Database username
     *
     * @param string $pass Database password
     *
     * @param string $host Database hostname
     *
     * @param string $name Database name
     */
    public function __construct(string $user = '', string $pass = '', string $host = '', string $name = '') {
        global $core;

        if (empty($user) || empty($pass) || empty($host) || empty($name)) {
            $this->driver  = $core->config->getDB('driver');
            $this->host    = $core->config->getDB('host');
            $this->user    = $core->config->getDB('user');
            $this->pass    = $core->config->getDB('pass');
            $this->name    = $core->config->getDB('name');
            $this->charset = $core->config->getDB('charset');
        } else {
            $this->host = $host;
            $this->user = $user;
            $this->pass = $pass;
            $this->name = $name;
        }

        $this->connect();
    }

    /**
     * Method to get directly a link to the database
     *
     * @return PDO Database link
     */
    public static function getInstance(): PDO {
        $odb = new ODB();
        if ($odb->link !== null) {
            return $odb->link;
        }

        throw new Exception('Database connection could not be established.');
    }

    /**
     * Call db_container for a connection to the database. Opens a new one if there is no connection available.
     *
     * @return bool Result of the operation
     */
    public function connect(): bool {
        global $core;

        if (!is_null($this->connection_index)) {
            $connection = $core->db_container->getConnectionByIndex($this->connection_index);
            if ($connection !== null) {
                $this->connection_index = $connection['index'];
                $this->link = $connection['link'];
            }
        } else {
            try {
                $connection = $core->db_container->getConnection($this->driver, $this->host, $this->user, $this->pass, $this->name, $this->charset);
                $this->connection_index = $connection['index'];
                $this->link = $connection['link'];
            } catch (PDOException $e) {
                throw new Exception('Connection failed: ' . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * Run a SQL command against the database.
     *
     * @param string $sql SQL command to execute.
     * @param array<array-key, mixed> $params Parameters bound to the SQL command.
     *
     * @return bool Result of the operation.
     *
     * @throws \Exception If the query cannot be executed.
     */
    public function query(string $sql, array $params = []): bool {
        try {
            $this->stmt = $this->link->prepare($sql);
            $this->stmt->execute($params);
            $this->result = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->result_index = 0;
            return true;
        } catch (PDOException $e) {
            throw new Exception('Query error: ' . $e->getMessage());
        }
    }

    /**
     * Method to obtain the number of results
     *
     * @return int Number of results
     */
    public function count(): int {
        return count($this->result);
    }

    /**
     * Get the next result row.
     *
     * @return array<string, mixed>|null Next result row or null when exhausted.
     */
    public function next(): ?array {
        if ($this->result_index < count($this->result)) {
            return $this->result[$this->result_index++];
        }
        return null;
    }

    /**
     * Get all result rows.
     *
     * @return array<int, array<string, mixed>> Result rows.
     */
    public function all(): array {
        return $this->result;
    }

    /**
     * Return database information safe for debugging.
     *
     * @return array Database information with sensitive values hidden.
     */
    public function __debugInfo(): array {
        $info = get_object_vars($this);

        if (isset($info['pass'])) {
            $info['pass'] = '[HIDDEN]';
        }

        if (isset($info['connection_index'])) {
            $info['connection_index'] = '[HIDDEN]';
        }

        return $info;
    }
}
