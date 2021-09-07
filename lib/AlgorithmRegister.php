<?php

namespace AlgorithmRegister;

class AlgorithmRegister
{

    private $_storageDir;
    private $_knownMaildomains;
    private $_uuidServiceUrl;
    private $_metadataStandardUrl;

    private function _createToken()
    {
        $chars = "ABCDEF0123456789";
        $token = "";
        while (strlen($token) < 20) {
            $token .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $token;
    }

    private function _getUuid()
    {
        return json_decode(file_get_contents($this->_uuidServiceUrl))[0]; // FIXME create locally
    }

    public function __construct($storageDir, $knownMaildomains, $uuidServiceUrl, $metadataStandardUrl)
    {
        $this->_storageDir = $storageDir;
        $this->_knownMaildomains = $knownMaildomains;
        $this->_uuidServiceUrl = $uuidServiceUrl;
        $this->_metadataStandardUrl = $metadataStandardUrl;
    }

    public function listApplications()
    {
        $schema = json_decode(file_get_contents($this->_metadataStandardUrl), true);
        $applications = [];
        if (($fp = fopen($this->_storageDir . "events.csv", "r")) !== FALSE) {
            $keys = fgetcsv($fp, 1000, ',');
            while (($values = fgetcsv($fp, 1000, ',')) !== false) {
                $event = array_combine($keys, $values);
                $basics = $schema["required"];
                // FIXME: load from the standard?
                //$basics = ["id", "name", "organization", "department", "revision_date", "staus", "type", "category", "contact_person_email", "uri"];
                if (in_array($event["field"], $basics) && $event["attribute"] === "value") {
                    $applications[$event["id"]][$event["field"]] = $event["value"];
                }
                if ($event["action"] === "delete") {
                    unset($applications[$event["id"]]);
                }
            }
            fclose($fp);
        }
        return array_values($applications);
    }

    public function listEvents($id)
    {
        $events = [];
        if (($fp = fopen($this->_storageDir . "events.csv", "r")) !== FALSE) {
            $keys = fgetcsv($fp, 1000, ',');
            while (($values = fgetcsv($fp, 1000, ',')) !== false) {
                $event = array_combine($keys, $values);
                if ($event["id"] === $id) {
                    $events[] = $event;
                }
            }
            fclose($fp);
        }
        return $events;
    }

    public function createApplication($data, $uri)
    {
        $contact = $data["contact"];
        $maildomain = array_pop(explode('@', $contact));
        if (!in_array($maildomain, $this->_knownMaildomains)) {
            return $response->withStatus(403);
        }

        $schema = json_decode(file_get_contents($this->_metadataStandardUrl), true);
        $application = $schema["properties"];

        foreach ($schema["required"] as $field) {
            if (!empty($application[$field]["const"])) {
                $application[$field]["value"] = $application[$field]["const"];
            } else {
                $application[$field]["value"] = $data[$field];
            }
            // FIXME: if the data is not there, throw a hissy fit
        }

        $application["id"]["value"] = $this->_getUuid();
        $application["url"]["value"] = "{$uri}/{$application["id"]["value"]}";

        $token = $this->_createToken();
        $application["hash"]["value"] = password_hash($token, PASSWORD_DEFAULT);
        $this->_storeApplication($application["id"]["value"], $application, "create");

        $application["token"]["value"] = $token; // do not store! listen very carefully, we'll return this only once

        return $application;
    }

    public function readApplication($id)
    {
        return $this->_loadApplication($id);
    }

    public function updateApplication($id, $values, $token)
    {
        $application = $this->_loadApplication($id);
        if (password_verify($token, $application["hash"]["value"])) {
            $changes = [];
            foreach ($values as $key => $value) {
                if ($application[$key]["value"] !== $value) {
                    $changes[$key]["value"] = $value;
                }
                $application[$key]["value"] = $value;
            }
            // optimization: only store changed values
            $this->_storeApplication($id, $changes, "update");
        }
        return $application;
    }

    public function deleteApplication($id, $token)
    {
        $application = $this->_loadApplication($id);
        if (password_verify($token, $application["hash"]["value"])) {
            $this->_deleteApplication($id);
        }
    }

    private function _loadApplication($id)
    {
        $application = [];
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
                $application[$event["field"]][$event["attribute"]] = $event["value"];
            }
            fclose($fp);
        }
        return $application;
    }

    private function _storeApplication($id, $application, $action)
    {
        $timestamp = date("Y-m-d H:i:s");
        foreach ($application as $field => $attributes) {
            foreach ($attributes as $attribute => $value) {
                $txt = "\"{$id}\",\"{$action}\",\"{$field}\",\"{$attribute}\",\"{$value}\",\"{$timestamp}\"";
                file_put_contents($this->_storageDir . "events.csv", $txt.PHP_EOL, FILE_APPEND);
            }
        }
    }

    private function _deleteApplication($id)
    {
        $timestamp = date("Y-m-d H:i:s");
        $txt = "\"{$id}\",\"delete\", , , ,\"{$timestamp}\"";
        file_put_contents($this->_storageDir . "events.csv", $txt.PHP_EOL, FILE_APPEND);
    }
}