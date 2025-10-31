<?php

namespace Mc\Sql;

use \Mc\Sql\Database;

/**
 * Simple CRUD implementation
 */
class Crud
{
    private $db;
    private $table;
    private $key;

    /**
     * crud constructor, must be passed a database object and a table name
     * the key is the primary key of the table, defaults to 'id' 
     * @param database $db
     * @param string $table
     * @param string $key
     */
    public function __construct(database $db, string $table, $key = "id")
    {
        $this->db = $db;
        $this->table = $table;
        $this->key = $key;
    }

    /**
     * insert a new record. Returns the id of the new record
     *
     * @param array|object $data
     * @return string|false
     */
    public function insert(array|object $data): string|false
    {
        $data = (array)$data;
        return $this->db->insert($this->table(), $data);
    }

    /**
     * select a record by id / key
     *
     * @param int|string $id
     * @return array
     */
    public function select(int|string $id): array
    {
        $result = $this->db->select($this->table(), ["*"], [$this->key() => $id], database::LIMIT1);
        if (count($result) === 0) {
            return [];
        }
        return $result[0];
    }

    /**
     * select <b>$limit</b> records from <b>$offset</b> record.
     *
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function all(int $offset = 0, int $limit = 100): array
    {
        return $this->db->select($this->table(), ["*"], [], ["offset" => $offset, "limit" => $limit]);
    }

    /**
     * update a record by id / key
     * parameter <b>$data</b> must include the key
     *
     * @param array|object $data
     * @return array
     */
    public function update(array|object $data): array
    {
        $data = (array)$data;
        return $this->db->update($this->table(), $data, [$this->key() => $data[$this->key()]]);
    }

    /**
     * if $data object contains key property, table will be
     * updated, otherwise new line will be inserted.
     *
     * @param array|object $data
     * @return array if object is updated
     * @return string if object is inserted, the id of the new object
     * @return false if object is not inserted or updated
     */
    public function insertOrUpdate(array|object $data): array|string|false
    {
        /// no key - insert object
        if (empty($data[$this->key])) {
            return $this->insert($data);
        }

        $key = $data[$this->key];
        echo "[debug] key found " . $key . PHP_EOL;
        $result = $this->select($key);
        /// object not found, insert object
        if (empty($result)) {
            return $this->insert($data);
        }

        /// update object
        return $this->update($data);
    }

    /**
     * delete a record by id / key
     * 
     * @param int|string $id
     */
    public function delete(int|string $id): void
    {
        $this->db->delete($this->table(), [$this->key() => $id]);
    }

    /**
     * return the table name, used for all CRUD operations
     * 
     * @return string
     */
    public function table(): string
    {
        return $this->table;
    }

    /**
     * return the key name, used for all CRUD operations
     * 
     * @return string
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * return number of lines in the associated table
     * 
     * @return int
     */
    public function count(): int
    {
        $result = $this->db->select($this->table(), ["count(*) as count"]);
        return intval($result[0]["count"]);
    }
}
