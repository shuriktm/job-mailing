<?php
/**
 * The helper functions to interact with database.
 */

namespace db;

use PDO;
use PDOStatement;

/**
 * Creates a database connection instance using PDO extension.
 *
 * @param array $config the database config.
 * @return PDO the connection instance.
 */
function connection(array $config): PDO
{
    return new PDO($config['dsn'], $config['username'], $config['password']);
}

/**
 * Determines the PDO data type for the param value.
 *
 * @param mixed $data the param value.
 * @return int the PDO data type.
 */
function param_type(mixed $data): int
{
    return match (gettype($data)) {
        'boolean' => PDO::PARAM_BOOL,
        'integer' => PDO::PARAM_INT,
        'NULL' => PDO::PARAM_NULL,
        'resource' => PDO::PARAM_LOB,
        default => PDO::PARAM_STR,
    };
}

/**
 * Executes SQL and returns PDO statement.
 *
 * @param PDO $db the database connection.
 * @param string $sql the SQL to select data.
 * @param array $params the parameters for SQL request.
 * @return PDOStatement the PDO statement object.
 */
function execute(PDO $db, string $sql, array $params = []): PDOStatement
{
    $statement = $db->prepare($sql);

    // Bind params
    foreach ($params as $name => $value) {
        $statement->bindValue($name, $value, param_type($value));
    }

    // Execute SQL
    $statement->execute();

    return $statement;
}

/**
 * Retrieves items as associative array from the database using SQL.
 *
 * @param PDO $db the database connection.
 * @param string $sql the SQL to select data.
 * @param array $params the parameters for SQL request.
 * @return array[] the list of items data.
 */
function all(PDO $db, string $sql, array $params = []): array
{
    // Execute SQL
    $statement = execute($db, $sql, $params);

    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Retrieves items as column from the database using SQL.
 *
 * @param PDO $db the database connection.
 * @param string $sql the SQL to select data.
 * @param array $params the parameters for SQL request.
 * @return array the list of values.
 */
function column(PDO $db, string $sql, array $params = []): array
{
    // Execute SQL
    $statement = execute($db, $sql, $params);

    return $statement->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Retrieves first row from the database using SQL.
 *
 * @param PDO $db the database connection.
 * @param string $sql the SQL to select data.
 * @param array $params the parameters for SQL request.
 * @return array the item data.
 */
function one(PDO $db, string $sql, array $params = []): array
{
    // Execute SQL
    $statement = execute($db, $sql, $params);

    return $statement->fetch(PDO::FETCH_ASSOC);
}

/**
 * Retrieves a scalar value from the database using SQL.
 *
 * @param PDO $db the database connection.
 * @param string $sql the SQL to select data.
 * @param array $params the parameters for SQL request.
 * @return mixed the scalar value.
 */
function scalar(PDO $db, string $sql, array $params = []): mixed
{
    // Execute SQL
    $statement = execute($db, $sql, $params);

    return $statement->fetchColumn();
}

/**
 * Inserts or updates existing data items in the database using primary key.
 *
 * @param PDO $db the database connection.
 * @param string $table the table name.
 * @param string $pk the table primary key.
 * @param array[] $rows the data items to be updated.
 * @return int the number of affected items.
 */
function upsert(PDO $db, string $table, string $pk, array $rows = []): int
{
    $first = reset($rows);
    if (!is_array($first)) {
        return 0;
    }

    // Field names
    $keys = array_keys($first);

    $params = [];
    $values = [];

    $num = 0;
    foreach ($rows as $row) {
        $insert = [];
        foreach ($row as $name => $value) {
            $param = ":$name$num";
            $params[$param] = $value;
            $insert[$name] = $param;
        }
        $values[] = '(' . implode(', ', $insert) . ')';
        $num++;
    }

    // Insert values
    $sql = ["INSERT INTO `$table` (" . implode(', ', array_map(fn($key) => "`$key`", $keys)) . ")"];
    $sql[] = 'VALUES ' . implode(', ', $values) . ' AS item';

    // Update values if exists (except primary key)
    unset($keys[$pk]);
    $sql[] = 'ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($key) => "`$key` = item.$key", $keys));

    // Prepare SQL
    $sql = implode(' ', $sql);

    $statement = $db->prepare($sql);

    // Bind params
    foreach ($params as $name => $value) {
        $statement->bindValue($name, $value, param_type($value));
    }

    // Execute SQL
    $statement->execute();

    return $statement->rowCount();
}

/**
 * Deletes existing data items from the database using primary key.
 *
 * @param PDO $db the database connection.
 * @param string $table the table name.
 * @param string $pk the table primary key.
 * @param array $ids the primary keys of items to be deleted.
 * @return int the number of affected items.
 */
function delete(PDO $db, string $table, string $pk, array $ids = []): int
{
    if (!$ids) {
        return 0;
    }

    $params = [];
    $values = [];

    $num = 0;
    foreach ($ids as $id) {
        $param = ":$pk" . $num++;
        $params[$param] = $id;
        $values[] = $param;
    }

    // Prepare SQL
    $sql = "DELETE FROM `$table` WHERE `$pk` IN (" . implode(', ', $values) . ")";

    $statement = $db->prepare($sql);

    // Bind params
    foreach ($params as $name => $value) {
        $statement->bindValue($name, $value, param_type($value));
    }

    // Execute SQL
    $statement->execute();

    return $statement->rowCount();
}
