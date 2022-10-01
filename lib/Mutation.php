<?php

namespace Tiltshift\Algoritmeregister;

class Mutation
{
    private $_id;
    private $_method;
    private $_key;
    private $_value;
    private $_timestamp;
    
    public function __construct($id, $method, $key, $value, $timestamp)
    {
        $this->_id = $id;
        $this->_method = $method;
        $this->_key = $key;
        $this->_value = $value;
        $this->_timestamp = $timestamp;
    }

    public function getHeadersString()
    {
        return '"id","method","key","value","timestamp"';
    }

    public function toString()
    {
        return "\"{$id}\",\"delete\",,,\"{$timestamp}\"";
    }
}
