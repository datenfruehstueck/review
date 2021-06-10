<?php

namespace ReviewOrganizer;

abstract class AbstractModel {
	protected $db;
	protected $config;
	protected $table;
	protected $fields;
	public $uid;
	
	function __construct(&$db, &$config, $row) {
	    $this->db = $db;
	    $this->config = $config;

	    //read database-table fields
        $this->fields = [];
        $sql = 'SHOW COLUMNS FROM `'.$this->table.'`';
        while($column = $this->db->fetch_row($sql)) {
            $this->fields[] = $column['Field'];
        }

	    //initiate values
		foreach($row as $field => $value) {
			if(property_exists($this, $field)) {
                if(in_array($field, $this->config['database']['tables']) && is_numeric($value) && $value > 0) {
                    $class = 'ReviewOrganizer\\'.ucfirst($field);
                    $class_row = $this->db->fetch_single_row('SELECT * FROM `' . $field . '` WHERE uid = ' . intval($value) . ' LIMIT 1');
                    $this->$field = new $class($this->db, $this->config, $class_row);
                } else {
                    $this->$field = $value;
                }
			}
		}
	}
	
	function update() {
		$updates = [];
		foreach($this->fields as $field) {
			if(property_exists($this, $field)) {
                if($this->$field === NULL) {
                    $updates[] = '`' . $field . '` = NULL';
                } elseif(is_subclass_of($this->$field, 'ReviewOrganizer\\AbstractModel')) {
                    $updates[] = '`' . $field . '` = ' . intval($this->$field->uid);
                } else {
                    $updates[] = '`' . $field . '` = \'' . $this->db->escape($this->$field) . '\'';
                }
			}
		}
		if(count($updates) == 0) {
			return FALSE;
		}
		$sql = 'UPDATE `'.$this->table.'` SET '.implode(', ', $updates).' WHERE uid = '.intval($this->uid).' LIMIT 1';
		return $this->db->query($sql);
	}
	
	function remove() {
		$sql = 'DELETE FROM `'.$this->table.'` WHERE uid = '.intval($this->uid).' LIMIT 1';
		return $this->db->query($sql);
	}

	function nicely_split_csv($csv_column) {
	    $options = [];
	    foreach(explode(',', $this->$csv_column) as $option) {
	        $options[] = trim($option);
        }
	    return $options;
    }

    function is_the_same_as($other_model) {
        if(is_subclass_of($other_model, 'ReviewOrganizer\\AbstractModel')) {
            return $other_model->table == $this->table && $other_model->uid == $this->uid;
        } elseif(is_numeric($other_model)) {
            return $other_model == $this->uid;
        } else {
            return FALSE;
        }
    }

}
