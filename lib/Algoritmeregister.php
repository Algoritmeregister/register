<?php

namespace Tiltshift\Algoritmeregister;

class Algoritmeregister
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
        return json_decode(file_get_contents($this->_uuidServiceUrl))[0];
    }

    public function __construct($storageDir, $knownMaildomains, $uuidServiceUrl, $metadataStandardUrl)
    {
        $this->_storageDir = $storageDir;
        $this->_knownMaildomains = $knownMaildomains;
        $this->_uuidServiceUrl = $uuidServiceUrl;
        $this->_metadataStandardUrl = $metadataStandardUrl;
    }

    public function listToepassingen()
    {
        $toepassingen = [];
        if (($fp = fopen($this->_storageDir . "events.csv", "r")) !== FALSE) {
            $keys = fgetcsv($fp, 1000, ',');
            while (($values = fgetcsv($fp, 1000, ',')) !== false) {
                $event = array_combine($keys, $values);
                $basics = [
                    "id",
                    "name",
                    "organization",
                    "department",
                    "revision_date",
                    "status",
                    "type",
                    "category",
                    "contact_email",
                    "uri"
                ];
                if (in_array($event["property"], $basics)) {
                    $toepassingen[$event["id"]][$event["property"]] = $event["value"];
                }
                if ($event["action"] === "delete") {
                    unset($toepassingen[$event["id"]]);
                }
            }
            fclose($fp);
        }
        return array_values($toepassingen);
    }

    public function getEvents()
    {
        return file_get_contents($this->_storageDir . "events.csv");
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

    public function createToepassing($data, $uri)
    {
        $maildomain = array_pop(explode('@', $data["contact_email"]));
        if (!in_array($maildomain, $this->_knownMaildomains)) {
            return $response->withStatus(403);
        }

        $toepassing = $this->_loadToepassing();

        $values = [];
        foreach ($toepassing as $property => $details) {
            if ($details["category"] === "BASIC INFORMATION" && !empty($data[$property])) {
                $values[$property] = $data[$property];
            }
        }
        $values["schema"] = $this->_metadataStandardUrl;
        $values["revision_date"] = $data["revision_date"]; // what to do here
        $values["id"] = $this->_getUuid(); // auto for meta data
        $values["uri"] = "{$uri}/{$values["id"]}"; // auto for meta data

        $token = $this->_createToken();
        $values["hash"] = password_hash($token, PASSWORD_DEFAULT);
        $this->_storeToepassing($values["id"], $values, "create");
        $values["token"] = $token; // return once but do not store
        return $values;
    }

    public function readToepassing($id)
    {
        $toepassing = $this->_loadToepassing($id);
        return $toepassing;
    }

    public function updateToepassing($id, $values, $token)
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

    public function deleteToepassing($id, $token)
    {
        $toepassing = $this->_loadToepassing($id);
        if (password_verify($token, $toepassing["hash"]["value"])) {
            $this->_deleteToepassing($id);
        }
    }

    private function _loadToepassing($id = NULL)
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

    private function _storeToepassing($id, $toepassing, $action)
    {
        $timestamp = date("Y-m-d H:i:s");
        foreach ($toepassing as $property => $value) {
            $txt = "\"{$id}\",\"{$action}\",\"{$property}\",\"{$value}\",\"{$timestamp}\"";
            file_put_contents($this->_storageDir . "events.csv", $txt.PHP_EOL, FILE_APPEND);
        }
    }

    private function _deleteToepassing($id)
    {
        $timestamp = date("Y-m-d H:i:s");
        $txt = "\"{$id}\",\"delete\", , , ,\"{$timestamp}\"";
        file_put_contents($this->_storageDir . "events.csv", $txt.PHP_EOL, FILE_APPEND);
    }
}