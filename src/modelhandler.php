<?php

namespace ReviewOrganizer;

class ModelHandler {
	protected $db;
	protected $config;
	protected $table;
    public $sql_filter = '';
    public $sql_order = '';
    public $entries;

	function __construct(&$db, &$config, $table) {
		$this->db = $db;
		$this->config = $config;
		$this->entries = [];
		$this->table = $table;
	}

	function count() {
	    return count($this->entries);
    }

    function collect_entries() {
        $sql = 'SELECT * FROM `'.$this->table.'`';
        if($this->sql_filter != '') {
            $sql .= ' WHERE '.$this->sql_filter;
        }
        if($this->sql_order != '') {
            $sql .= ' ORDER BY '.$this->sql_order;
        }
        $class = 'ReviewOrganizer\\'.ucfirst($this->table);
        while($row = $this->db->fetch_row($sql)) {
            $this->entries[] = new $class($this->db, $this->config, $row);
        }
    }

    function get($index) {
	    if(isset($this->entries[$index])) {
	        return $this->entries[$index];
        } else {
	        return NULL;
        }
    }
	
	function get_entry_by_uid($uid) {
		foreach($this->entries as $entry) {
			if($entry->uid == $uid) {
				return $entry;
			}
		}
		return FALSE;
	}

	function get_entries_by_field($field, $value) {
	    $filtered_entries = [];
		foreach($this->entries as $entry) {
            if(is_subclass_of($entry->$field, 'ReviewOrganizer\\AbstractModel')) {
                if($entry->$field->is_the_same_as($value)) {
                    $filtered_entries[] = $entry;
                }
            } elseif ($entry->$field == $value) {
                $filtered_entries[] = $entry;
			}
		}
		return $filtered_entries;
	}

	function get_fields_from_table() {
		$fields = [];
		$sql = 'SHOW COLUMNS FROM `'.$this->table.'`';
		while($row = $this->db->fetch_row($sql)) {
			$fields[] = $row['Field'];
		}
		return $fields;
	}
	
	function add($model) {
		$inserts = [];
		foreach($this->get_fields_from_table() as $field) {
			if(property_exists($model, $field) && $field != 'uid') {
			    if(is_subclass_of($model->$field, 'ReviewOrganizer\\AbstractModel')) {
			        $inserts[$field] = $model->$field->uid;
                } else {
                    $inserts[$field] = '\''.$this->db->escape($model->$field).'\'';
                }
			}
		}
		if(count($inserts) == 0) {
			return NULL;
		}
		$sql = 'INSERT INTO `'.$this->table.'` (`'.implode('`, `', array_keys($inserts)).'`) VALUES ('.implode(', ', $inserts).')';
		if($this->db->query($sql)) {
			$model->uid = $this->db->get_last_insert_uid();
			$this->entries[] = $model;
			return $model;
		} else {
			return NULL;
		}
	}
}
