<?php
/**
 * 数据库操作类（PDO 封装）
 * 兼容 PHP 7.1+ / MySQL 5.5+
 */

class DB
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        );
        $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function pdo()
    {
        return $this->pdo;
    }

    public function query($sql, $params = array())
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = array())
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetch($sql, $params = array())
    {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchColumn($sql, $params = array())
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    public function execute($sql, $params = array())
    {
        return $this->query($sql, $params);
    }

    public function insert($table, $data)
    {
        $fields = array_keys($data);
        $cols = '`' . implode('`,`', $fields) . '`';
        $marks = implode(',', array_fill(0, count($fields), '?'));
        $sql = 'INSERT INTO `' . DB_PREFIX . $table . '` (' . $cols . ') VALUES (' . $marks . ')';
        $this->query($sql, array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = array())
    {
        $sets = array();
        $params = array();
        foreach ($data as $k => $v) {
            $sets[] = '`' . $k . '` = ?';
            $params[] = $v;
        }
        $params = array_merge($params, $whereParams);
        $sql = 'UPDATE `' . DB_PREFIX . $table . '` SET ' . implode(',', $sets) . ' WHERE ' . $where;
        return $this->query($sql, $params)->rowCount();
    }

    public function delete($table, $where, $whereParams = array())
    {
        $sql = 'DELETE FROM `' . DB_PREFIX . $table . '` WHERE ' . $where;
        return $this->query($sql, $whereParams)->rowCount();
    }

    public function count($table, $where = '', $whereParams = array())
    {
        $sql = 'SELECT COUNT(*) FROM `' . DB_PREFIX . $table . '`';
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        return (int)$this->query($sql, $whereParams)->fetchColumn();
    }
}
