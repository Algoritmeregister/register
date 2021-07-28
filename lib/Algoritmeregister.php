<?php

namespace Tiltshift\Algoritmeregister;

class Algoritmeregister
{

    private $_storageDir;

    private function _createToken()
    {
        $chars = "ABCDEF0123456789";
        $token = "";
        while (strlen($this->_token) < 20) {
            $token .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $token;
    }

    private function _getUuid()
    {
        return json_decode(file_get_contents("https://www.uuidtools.com/api/generate/v1"))[0];
    }

    private function _transformToIndexed($metadata)
    {
        $indexed = [];
        foreach ($metadata as $field) {
            $indexed[$field["eigenschap"]] = $field;
        }
        return $indexed;
    }

    public function __construct($storageDir)
    {
        $this->_storageDir = $storageDir;
    }

    public function listToepassingen()
    {
        $toepassingen = [];
        if (($fp = fopen($this->_storageDir . "events.csv", "r")) !== FALSE) {
            $keys = fgetcsv($fp, 1000, ',');
            while (($values = fgetcsv($fp, 1000, ',')) !== false) {
                $event = array_combine($keys, $values);
                $basics = ["id", "naam", "organisatie", "afdeling", "herziening", "status", "type", "contact", "uri"];
                if (in_array($event["field"], $basics) && $event["attribute"] === "waarde") {
                    $toepassingen[$event["id"]][$event["field"]] = $event["value"];
                }
            }
            fclose($fp);
        }
        return array_values($toepassingen);
    }

    public function createToepassing($data, $uri)
    {
        $toepassing = $this->_loadToepassing();
        $toepassing["naam"]["waarde"] = $data["naam"];
        $toepassing["organisatie"]["waarde"] = $data["organisatie"];
        $toepassing["afdeling"]["waarde"] = $data["afdeling"];
        $toepassing["contact"]["waarde"] = $data["contact"];
        $toepassing["type"]["waarde"] = $data["type"];
        $toepassing["status"]["waarde"] = $data["status"];
        $toepassing["herziening"]["waarde"] = $data["herziening"];
        $toepassing["id"]["waarde"] = $this->_getUuid();
        $toepassing["uri"]["waarde"] = "{$uri}/{$toepassing["id"]["waarde"]}";
        $this->_storeToepassing($toepassing["id"]["waarde"], $toepassing, "create");
        return $toepassing;
    }

    public function readToepassing($id)
    {
        return $this->_loadToepassing($id);
    }

    public function updateToepassing($id, $values)
    {
        $toepassing = $this->_loadToepassing($id);
        $changes = [];
        foreach ($values as $key => $value) {
            if ($toepassing[$key]["waarde"] !== $value) {
                $changes[$key]["waarde"] = $value;
            }
            $toepassing[$key]["waarde"] = $value;
        }
        $this->_storeToepassing($id, $changes, "update"); // optimization: only store changed values
        return $toepassing;
    }

    private function _loadToepassing($id = NULL)
    {
        if (!$id) {
            return $this->_transformToIndexed(json_decode(file_get_contents("https://algoritmeregister.github.io/algoritmeregister-metadata-standaard/algoritmeregister-metadata-standaard.json"), true));
        }
        $toepassing = [];
        if (($fp = fopen($this->_storageDir . "events.csv", "r")) !== FALSE) {
            $keys = fgetcsv($fp, 1000, ',');
            while (($values = fgetcsv($fp, 1000, ',')) !== false) {
                $event = array_combine($keys, $values);
                if ($event["id"] !== $id) {
                    continue;
                }
                $toepassing[$event["field"]][$event["attribute"]] = $event["value"]; // FIXME use event action
            }
            fclose($fp);
        }
        return $toepassing;
        
    }

    private function _storeToepassing($id, $toepassing, $action)
    {
        $timestamp = date("Y-m-d H:i:s");
        foreach ($toepassing as $field => $attributes) {
            foreach ($attributes as $attribute => $value) {
                $txt = "\"{$id}\",\"{$action}\",\"{$field}\",\"{$attribute}\",\"{$value}\",\"{$timestamp}\"";
                file_put_contents($this->_storageDir . "events.csv", $txt.PHP_EOL, FILE_APPEND);
            }
        }
    }
}