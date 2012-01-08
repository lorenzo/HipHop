<?php

App::uses('Mysql', 'Model/Datasource/Database');
class HpMysql extends Mysql {

/**
 * Builds a map of the columns contained in a result
 *
 * @param PDOStatement $results
 * @return void
 */
	public function resultSet($results) {
		$this->map = array();
		$numFields = $results->columnCount();
		$index = 0;

		while ($numFields-- > 0) {
			$column = $results->getColumnMeta($index);
			if (empty($column['native_type'])) {
				$type = ($column['len'] == 1) ? 'boolean' : 'string';
			} else {
				$type = $column['native_type'];
			}
			if (strpos($column['name'], '__')) {
				list($column['table'], $column['name']) = explode('__', $column['name']);
			}
			if (!empty($column['table']) && strpos($column['name'], $this->virtualFieldSeparator) === false) {
				$this->map[$index++] = array($column['table'], $column['name'], $type);
			} else {
				$this->map[$index++] = array(0, $column['name'], $type);
			}
		}
	}

/**
* Generates the fields list of an SQL query.
*
* @param Model $model
* @param string $alias Alias table name
* @param mixed $fields
* @param boolean $quote
* @return array
*/
	public function fields($model, $alias = null, $fields = array(), $quote = true) {
		if (empty($alias)) {
			$alias = $model->alias;
		}
		$fields = parent::fields($model, $alias, $fields, false);

		if (!$quote) {
			return $fields;
		}
		$count = count($fields);

		if ($count >= 1 && !preg_match('/^\s*COUNT\(\*/', $fields[0])) {
			$result = array();
			for ($i = 0; $i < $count; $i++) {
				if (!preg_match('/^.+\\(.*\\)/', $fields[$i]) && !preg_match('/\s+AS\s+/', $fields[$i])) {
					if (substr($fields[$i], -1) == '*') {
						if (strpos($fields[$i], '.') !== false && $fields[$i] != $alias . '.*') {
							$build = explode('.', $fields[$i]);
							$AssociatedModel = $model->{$build[0]};
						} else {
							$AssociatedModel = $model;
						}

						$_fields = $this->fields($AssociatedModel, $AssociatedModel->alias, array_keys($AssociatedModel->schema()));
						$result = array_merge($result, $_fields);
						continue;
					}

					$prepend = '';
					if (strpos($fields[$i], 'DISTINCT') !== false) {
						$prepend = 'DISTINCT ';
						$fields[$i] = trim(str_replace('DISTINCT', '', $fields[$i]));
					}

					if (strrpos($fields[$i], '.') === false) {
						$fields[$i] = $prepend . $this->name($alias) . '.' . $this->name($fields[$i]) . ' AS ' . $this->name($alias . '__' . $fields[$i]);
					} else {
						$build = explode('.', $fields[$i]);
						$fields[$i] = $prepend . $this->name($build[0]) . '.' . $this->name($build[1]) . ' AS ' . $this->name($build[0] . '__' . $build[1]);
					}
				} else {
					$fields[$i] = preg_replace_callback('/\(([\s\.\w]+)\)/',  array(&$this, '_quoteFunctionField'), $fields[$i]);
				}
				$result[] = $fields[$i];
			}
			return $result;
		}
		return $fields;
	}

/**
 * Auxiliary function to quote matched `(Model.fields)` from a preg_replace_callback call
 * Quotes the fields in a function call.
 *
 * @param string $match matched string
 * @return string quoted string
 */
	protected function _quoteFunctionField($match) {
		$prepend = '';
		if (strpos($match[1], 'DISTINCT') !== false) {
			$prepend = 'DISTINCT ';
			$match[1] = trim(str_replace('DISTINCT', '', $match[1]));
		}
		$constant = preg_match('/^\d+|NULL$/i', $match[1]);

		if (!$constant && strpos($match[1], '.') === false) {
			$match[1] = $this->name($match[1]);
		} elseif (!$constant) {
			$parts = explode('.', $match[1]);
			if (!Set::numeric($parts)) {
				$match[1] = $this->name($match[1]);
			}
		}
		return '(' . $prepend . $match[1] . ')';
	}

}