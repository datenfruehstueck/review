<?php

namespace ReviewOrganizer;

class Database {
    protected $db;
    protected $results;
    protected $last_sql_statement;

    function __construct(&$config) {
        $this->config = $config;
        $this->results = [];
        $this->db = new \mysqli($config['database']['host'], $config['database']['user'], $config['database']['password'], $config['database']['name']);
        $this->query('SET NAMES utf8');
    }

    function query($query) {
        $this->last_sql_statement = $query;
        return $this->db->query($query);
    }

    function fetch_single_row($query) {
        $result = $this->query($query);
        if($result->num_rows == 0) {
            return NULL;
        } else {
            return $result->fetch_assoc();
        }
    }

    function fetch_row($query) {
        if(!isset($this->results[$query])) {
            $this->results[$query] = NULL;
        }
        if($this->results[$query] === NULL) {
            $this->results[$query] = $this->query($query);
            if(!$this->results[$query]) {
                unset($this->results[$query]);
                return NULL;
            }
        }
        $resulting_row = $this->results[$query]->fetch_assoc();
        if($resulting_row === NULL) {
            unset($this->results[$query]);
            return NULL;
        } else {
            return $resulting_row;
        }
    }

    function escape($var) {
        if($var === NULL) {
            return NULL;
        } else {
            return $this->db->real_escape_string($var);
        }
    }

    function get_last_insert_uid() {
        if($this->db->insert_id > 0) {
            return $this->db->insert_id;
        } else {
            return FALSE;
        }
    }
}