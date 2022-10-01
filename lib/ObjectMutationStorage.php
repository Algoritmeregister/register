<?php

namespace Tiltshift\Algoritmeregister;

class ObjectMutationStorage
{

    private $_csvStorageFile;

    public function __construct($storageDir)
    {
        $this->_csvStorageFile = $storageDir . "storage.csv";
    }

    public function getCsvFileRaw()
    {
        return file_get_contents($this->_csvStorageFile);
    }

    public function getSnapshot($fields, $timestamp = NULL)
    {
        $objects = [];
        if (($fp = fopen($this->_csvStorageFile, "r")) !== FALSE) {
            $keys = fgetcsv($fp, 1000, ',');
            while (($values = fgetcsv($fp, 1000, ',')) !== false) {
                $mutation = array_combine($keys, $values);
                // FIXME check timestamp
                if (!$objects[$mutation["id"]]) {
                    $objects[$mutation["id"]];
                }
                if (in_array($mutation["key"], $fields)) {
                    $objects[$mutation["id"]][$mutation["key"]] = $mutation["value"];
                }
                if ($mutation["method"] === "delete") {
                    unset($objects[$mutation["id"]]);
                }
            }
            fclose($fp);
        }
        return array_values($objects);
    }

    public function getMutations($id)
    {
        $mutations = [];
        if (($fp = fopen($this->_csvStorageFile, "r")) !== FALSE) {
            $keys = fgetcsv($fp, 1000, ',');
            while (($values = fgetcsv($fp, 1000, ',')) !== false) {
                $mutation = array_combine($keys, $values);
                if ($mutation["id"] === $id) {
                    $mutations[] = $mutation;
                }
            }
            fclose($fp);
        }
        return $mutations;
    }

    public function put($id, $values, $token)
    {
        $toepassing = $this->_loadToepassing($id);
        if (password_verify($token, $toepassing["hash"])) {
            $changes = [];
            foreach ($values as $key => $value) {
                if ($toepassing[$key] !== $value) {
                    $changes[$key] = $value;
                }
                $toepassing[$key] = $value;
            }
            $this->_storeToepassing($id, $changes, "update"); // optimization: only store changed values
        }
        return $toepassing;
    }

    public function update($id, $object, $action)
    {
        $timestamp = date("Y-m-d H:i:s");
        foreach ($toepassing as $property => $value) {
            $txt = "\"{$id}\",\"{$action}\",\"{$property}\",\"{$value}\",\"{$timestamp}\"";
            file_put_contents($this->_storageDir . "events.csv", $txt.PHP_EOL, FILE_APPEND);
        }
    }

    public function get($id = NULL)
    {
        if (!$id) {
            $schema = json_decode(file_get_contents($this->_metadataStandardUrl), true);
            return $schema["properties"];
        }
        $toepassing = [];
        if (($fp = fopen($this->_storageDir . "events.csv", "r")) !== FALSE) {
            $keys = fgetcsv($fp, 1000, ',');
            while (($values = fgetcsv($fp, 1000, ',')) !== false) {
                $event = array_combine($keys, $values);
                if ($event["id"] !== $id) {
                    continue;
                }
                if ($event["action"] === "delete") {
                    return null;
                }
                $toepassing[$event["property"]] = $event["value"];
            }
            fclose($fp);
        }
        return $toepassing;
    }

    public function delete($id)
    {
        $timestamp = date("Y-m-d H:i:s");
        $txt = "\"{$id}\",\"delete\",,,\"{$timestamp}\"";
        file_put_contents($this->_storageDir . "events.csv", $txt.PHP_EOL, FILE_APPEND);
    }
}
