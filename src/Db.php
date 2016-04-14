<?php
declare(strict_types = 1);
namespace Huskee\Bundle;
class Db
{
	private $_dbh;
	private $_queries = array();
	private $_results = array();

	function __construct(string $host, string $user, string $password, string $dbName)
	{
        if (!$host || !$user || !$password || !$dbName)
            $this->_error('Invalid init', 503);

		if (!($this->_dbh = mysqli_connect($host, $user, $password)))
			$this->_error('Can\'t connect to the database server', 503);
		$this->connect($dbName);
		mysqli_set_charset($this->_dbh, 'utf8');
	}

	public function connect(string $dbName)
	{
		if (!mysqli_select_db($this->_dbh, $dbName))
			$this->_error(sprintf('Can\'t connect to the specified database', $dbName), 503);
	}

    public function escape($string, bool $escapeLike = true)
    {
        if ($escapeLike)
            $string = str_replace(array("%"), array("\\%"), $string);

        $string = mb_convert_encoding((string) $string, 'UTF-8', 'UTF-8');
        return mysqli_real_escape_string($this->_dbh, $string);
    }

	public function select(string $fields, string $from, array $whereArray, string $limit = '1', string $join = '', string $orderBy = '')
	{
        if (is_array($whereArray) && $whereArray) {
            $where = array();
            if (isset($whereArray[0])) {
                foreach ($whereArray as $key => $value) {
                    if (is_numeric($key)) {
                        if (!isset($value[0]))
                            $value = array($value);
                        foreach ($value as $val)
                            $where[] = '(' . implode(' AND ', $this->_prepareFields($val)) . ')';
                        unset($whereArray[$key]);
                    }
                }

                $where = implode(' OR ', $where);
                if ($whereArray)
                    $where = implode(' AND ', $this->_prepareFields($whereArray) + array('where' => '(' . $where . ')'));
            } else
                $where = implode(' AND ', $this->_prepareFields($whereArray));
            $where = 'WHERE ' . $where;
        } else
            $where = '';

		$limit = explode(',', $limit);
		if (isset($limit[1])) {
			$offset = $limit[0];
			$count = $limit[1] + 0;
		} else {
			$offset = 0;
			$count = $limit[0] + 0;
		}

        //echo "SELECT $fields FROM $from $join $where" . ($orderBy ? " ORDER BY $orderBy" : '') . ($count != 0 ? " LIMIT $offset,$count"  : '') . '<br />';
		$this->_query("SELECT $fields FROM $from $join $where" . ($orderBy ? " ORDER BY $orderBy" : '') . ($count != 0 ? " LIMIT $offset,$count"  : ''), $count == 0 || $count > 1 ? true : false);
	}

	public function insert(string $table, array $fieldsAndValuesArray, array $onDuplicateKeyArray = array())
	{
		$this->_query($this->prepare('insert', $table, $fieldsAndValuesArray, array(), $onDuplicateKeyArray));
		array_pop($this->_queries);
		return mysqli_insert_id($this->_dbh);
	}

	public function update(string $table, array $fieldsAndValuesArray, array $whereArray)
	{
		$this->_query($this->prepare('update', $table, $fieldsAndValuesArray, $whereArray));
		array_pop($this->_queries);
	}

	public function delete(string $table, array $whereArray)
	{
		$this->_query($this->prepare('delete', $table, array(), $whereArray));
		array_pop($this->_queries);
	}

	public function prepare(string $operation, string $table, array $fieldsAndValuesArray, array $whereArray = array(), array $onDuplicateKeyArray = array()) : string
	{
		$sql = "$operation ";
		switch ($operation) {
			case 'update':
				$sql .= "$table SET ";
				$sql .= implode(',', $this->_prepareFields($fieldsAndValuesArray));
				$sql .= " WHERE " . implode(' AND ', $this->_prepareFields($whereArray));
			break;
			case 'insert':
				$sql .= "INTO $table SET ";
				$sql .= implode(',', $this->_prepareFields($fieldsAndValuesArray));
				if ($onDuplicateKeyArray){
					if ($onDuplicateKeyArray == 'ignore')
						$sql = str_ireplace('insert into', 'insert ignore into', $sql);
					else
						$sql .= 'ON DUPLICATE KEY UPDATE ' . implode(',', $this->_prepareFields($onDuplicateKeyArray));
				}
			break;

			case 'delete':
				$sql .= "FROM $table WHERE " . implode(' AND ', $this->_prepareFields($whereArray));
			break;

			default:
				$this->_error('Invalid operation', 501);
		}

		$this->_queries[] = $sql;

		return $sql;
	}

	public function commit()
	{
		foreach ($this->_queries as $value)
			$this->_query($value);

		$this->_queries = array();
		return true;
	}

	public function getResults(string $key = '')
	{
		$return = $this->_results;
		$this->_results = array();

		if ($key)
			$return = isset($return[$key]) ? $return[$key] : null;

		return $return;
	}

	public function query(string $query, bool $multi = false)
	{
		$this->_query($query, $multi);
	}

	private function _prepareFields(array $fieldsAndValuesArray) : array
	{
		if (!is_array($fieldsAndValuesArray))
            $this->_error('Invalid parameters', 500);

		$return = array();

		foreach ($fieldsAndValuesArray as $field => $value) {
			if (!$field)
                $this->_error('Invalid field name', 500);

			$sql = '';
			$operation = '=';

			$val = is_array($value) ? $value[1] : $value;
			$val = $this->escape($val, false);

			$field = explode('.', $field);
			$field = isset($field[1]) ? "{$field[0]}.`{$field[1]}`" : "`{$field[0]}`";

			if (is_array($value)) {
				if (in_array($value[0], array('+=', '-=')))
					$sql = "$field = $field " . substr($value[0], 0 ,-1) . " $val";
				$operation = $value[0];
			}
            
            $operation = (string) $operation;
            
			if (!$sql) {
				$sql = $operation === 'if' ? "$field = $operation " : "$field $operation ";
                if (in_array(strtolower($operation), array('like', 'not like'), true)) {
                    $val = $this->escape($val);
                    if (isset($value[2]) && $value[2])
                        $sql .= "'%{$val}%'";
					else
						$sql .= "'{$val}'";
                } elseif (in_array(strtolower($operation), array('in', 'not in', 'if'), true)) {
					$val = str_replace("\\'", "'", $val);
                    if (strpos($val, "\\") !== false)
                        $this->_error('Something went wrong', 501);
                    $sql .=  "($val)";
				} else
                    $sql .= "'$val'";
			}

			$return[] = $sql;
		}

		return $return;
	}

	private function _query(string $query, bool $multi = false)
	{
		$result = mysqli_query($this->_dbh, $query);
		if ($result === false) {
            $this->_error('Invalid query: ' . $query, 500);
			exit;
		}
		is_object($result) ? $this->_setResults($result, $multi) : $this->_results = mysqli_affected_rows($this->_dbh);
	}

	private function _setResults(\mysqli_result $result, bool $multi)
	{
		$this->_results = array();
		$result->data_seek(0);
		if ($multi) {
			while ($row = $result->fetch_assoc())
				$this->_results[] = $row;
			mysqli_free_result($result);
		} else
			$this->_results = $result->fetch_assoc();
	}

    private function _error(string $details, int $code)
    {
        throw new \Exception ($details, $code);
    }
}
