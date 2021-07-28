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
        if (($fp = fopen($this->_storageDir . "index.csv", "r")) !== FALSE) {
            $keys = fgetcsv($fp, 1000, ',');
            while (($values = fgetcsv($fp, 1000, ',')) !== false) {
                $toepassingen[] = array_combine($keys, $values);
            }
            fclose($fp);
        }
        return $toepassingen;
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
        $toepassing["uuid"]["waarde"] = $this->_getUuid();
        $this->_storeToepassing($toepassing["uuid"]["waarde"], $toepassing);

        // FIXME remove later
        $organisatie = $data["organisatie"];
        $afdeling = $data["afdeling"];
        $naam = $data["naam"];
        $contact = $data["contact"];
        $type = $data["type"];
        $status = $data["status"];
        $herziening = $data["herziening"];
        $this->_storeIndex($toepassing["uuid"]["waarde"], $organisatie, $afdeling, $naam, $type, $status, $herziening, $contact, $hash, "{$uri}/{$toepassing["uuid"]["waarde"]}");

        return $toepassing;
    }

    public function readToepassing($id)
    {
        return $this->_loadToepassing($id);
    }

    public function updateToepassing($id, $values)
    {
        //file_put_contents(__DIR__ . "/../storage/log.txt", print_r($values, true).PHP_EOL, FILE_APPEND);
        $toepassing = $this->_loadToepassing($id);
        foreach ($values as $key => $value) {
            $toepassing[$key]["waarde"] = $value;
        }
        $this->_storeToepassing($id, $toepassing);
        return $toepassing;
    }

    private function _loadToepassing($id = NULL)
    {
        return !$id ?
            $this->_transformToIndexed(json_decode(file_get_contents("https://algoritmeregister.github.io/algoritmeregister-metadata-standaard/algoritmeregister-metadata-standaard.json"), true)) :
            json_decode(file_get_contents(__DIR__ . "/../storage/{$id}." . md5($id) . ".json"), true);
    }

    private function _storeToepassing($id, $toepassing)
    {
        file_put_contents(__DIR__ . "/../storage/{$id}." . md5($id) . ".json", json_encode($toepassing));
    }

    private function _storeIndex($uuid, $organisatie, $afdeling, $naam, $type, $status, $herziening, $contact, $hash, $uri)
    {
        // FIXME remove later
        $txt = "\"{$uuid}\",\"{$organisatie}\",\"{$afdeling}\",\"{$naam}\",\"{$type}\",\"{$status}\",\"{$herziening}\",\"{$contact}\",\"{$hash}\",\"{$uri}\"";
        $myfile = file_put_contents($this->_storageDir . "index.csv", $txt.PHP_EOL, FILE_APPEND);
    }

}