<?php

namespace Application\Service;

use Zend\Http\Client\Adapter\Curl;
use Zend\Http\Request;
use Zend\Http\Client;

class Box
{
    protected $httpClient;
    protected $baseUrl = 'https://api.box.com/2.0';

    public function getHttpClient()
    {
        return clone $this->httpClient;
    }

    public function setHttpClient(Client $client)
    {
        $this->httpClient = $client;
    }

    public function __construct()
    {
        $adapter = new Curl;
        $client = new Client;

        $client->setHeaders(array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $_SESSION['access_token'],
        ));

        $adapter->setOptions(array(
            'curloptions' => array(
                CURLOPT_FOLLOWLOCATION => true,
            ),
        ));

        $client->setAdapter($adapter);

        $this->setHttpClient($client);
    }

    public function syncDown($localFolder, $remoteFolder)
    {
        $remote = $this->getFolderByName($remoteFolder);
        if (!$remote) {
            throw new \Exception("Remote folder $remoteFolder does not exist");
        }

        if (!file_exists($localFolder)) {
            mkdir($localFolder);
        }

        foreach ($remote->item_collection->entries as $item) {
            switch ($item->type) {
                case 'file':
                    $localFile = $localFolder . '/' . $item->name;

                    // Only sync if SHA1 does not match or no local file
                    if (file_exists($localFile)) {
                        if (sha1($localFile) == $item->sha1) {
                            continue;
                        }
                    }

                    $fileContents = $this->getHttpClient()
                        ->setMethod('GET')
                        ->setUri($this->baseUrl . '/files/' . $item->id . '/content')->send();

                    file_put_contents($localFile, $fileContents);

                    // Verify file
                    if (sha1($localFile) == $item->sha1) {
                        throw new \Exception("File $localFile did not match sha1 of remote after download");
                    }
                    break;

                case 'folder':
                    if (!file_exists($localFolder . '/' . $item->name)) {
                        mkdir($localFolder . '/' . $item->name);
                    }
                    $this->syncDown($localFolder . '/' . $item->name, $remoteFolder . '/' . $item->name);
                    break;

                default:
                    continue;
                    throw new \Exception("Unexpected item type " . $item->type);

            }
        }
    }

    /**
     * walk the folder hierarchy until we find the last match
     * returns false on first unmatched path
     */
    public function getFolderByName($path)
    {
        $root = $this->getFolderById(0);

        foreach(explode("/",$path) as $n => $name){
            // Trivial tests
            if(!$name) continue;
            $found = false;
            // Walk through our hierarchy and find the currently named target
            foreach($root->item_collection->entries as $item){
                if(!$found && $item->type == 'folder' && $item->name == $name){
                    // Set root to be the folder object of found ID
                    $root = $this->getFolderById($item->id);
                    $found = true;
                    break;
                }
            }
            if(!$found) return false;
        }
        return $root;
    }

    public function getFolderById($folderId)
    {
        $uri = "https://api.box.com/2.0/folders/$folderId";

        $result = $this->getHttpClient()->setMethod('GET')->setUri($uri)->send();
        $obj = json_decode($result->getBody());

        return $obj;
    }
}