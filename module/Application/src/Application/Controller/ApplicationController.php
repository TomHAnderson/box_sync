<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2013 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace Application\Controller;

use Zend\Console\ColorInterface;
use Zend\Console\Request as ConsoleRequest;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Console\Adapter\AdapterInterface as Console;
use Zend\Console\Prompt;

use Box\OAuth2\Client\Provider\Box;
use Box\OAuth2\Client\Grant\RefreshToken;
use Zend\Http\Request;
use Zend\Http\Client;

class ApplicationController extends AbstractActionController
{
    protected $stopped = false;

    public function __construct()
    {
        // Listen to the signals SIGTERM and SIGINT so that the worker can be killed properly. Note that
        // because pcntl_signal may not be available on Windows, we needed to check for the existence of the function
        if (function_exists('pcntl_signal')) {
           declare(ticks = 1);
           pcntl_signal(SIGTERM, array($this, 'handleSignal'));
           pcntl_signal(SIGINT, array($this, 'handleSignal'));
        }
    }

    public function setEventManager(EventManagerInterface $events)
    {
        parent::setEventManager($events);
        $events->attach('dispatch', function($e) {
            $request = $e->getRequest();
            if (!$request instanceof ConsoleRequest) {
                throw new \RuntimeException(sprintf(
                    '%s can only be executed in a console environment',
                    __CLASS__
                ));
            }
        }, 100);
        return $this;
    }

    public function handleSignal($signo)
    {
        switch($signo) {
            case SIGTERM:
            case SIGINT:
                $this->stopped = true;
                break;
        }
    }

    public function isStopped()
    {
        return $this->stopped;
    }

    public function syncAction()
    {
        $config = $this->getServiceLocator()->get('Config');
        $accessToken = $this->getRequest()->getParam('access_token');
        $refreshToken = $this->getRequest()->getParam('refresh_token');

        while (true) {
            // Check for external stop condition
            if ($this->isStopped()) {
                break;
            }

            // Verify accessToken is valid
            $request = new Request();
            $request->getHeaders()->addHeaders(array(
                'Authorization' => 'Bearer ' . $accessToken
            ));
            $request->setMethod('GET');
            $request->setUri('https://api.box.com/2.0/users/me');
            $client = new Client();

            $response = $client->dispatch($request);
            switch ($response->getStatusCode()) {
                case 401:
                    // Refresh token with refreshToken
                    $config = $this->getServiceLocator()->get('Config');
                    $provider = new Box($config['oauth2']);

                    try {
                        $grant = new RefreshToken();
                        $token = $provider->getAccessToken($grant, array('refresh_token' => $refreshToken));

echo "\nThe expires should not be more than one hour.\n";
print_r($token); die();

                    } catch (\League\OAuth2\Client\Exception\IDPException $e) {
                        die("Could not refresh the access_token or refresh_token.  Restart the application using bin/fetchOAuth2 then browse to http://localhost:8081\n");
                    } catch (\Exception $e) {
                        throw $e;
                    }
                case 200:
                    continue;
                default:
                    die('Error ' . $response->getStatusCode() . ' was unexpected and not handled');
            }

            die("\nAccess Token is valid\n");

        } # End working while loop
    }

    protected function receive($box, $config)
    {
        $source = $config['source'];
        $destination = $config['destination'];
        $archive = $config['archive'];
        $hotfolder = $config['hotfolder'];

        /**
         * Scan the MR Clipping From and copy to hotfolder
         * Business Rules:
         *  1) Scan the BOX.COM Account / Path and copy all the files onto local destination directory
         *  2) Verify copy is sha1 match
         *  2b) Remove the copy from the BOX.COM Account / Path
         *  3) Update Log entry for file
         */
        echo "Syncing Box.com folder $source to $destination" . PHP_EOL;
        if (!$folder = $box->getFolderByName($source)) {
            throw new \Exception($source . " folder was not found on box.com");
        }

        /**
         * @var $boxfile object
         */
        if($folder->item_collection->total_count > 0) {

            foreach($folder->item_collection->entries as $boxfile){
                if($boxfile->type != "file") continue;

                $asset = \Photobase\Asset::createFromFilename($boxfile->name);
                $remote_path = $source . "/" . $boxfile->name;

                // athleta have special handling
                $isAthleta = (stripos($source, 'athleta') !== false) ? true: false;
                /**
                 * Athleta assets are not copied so we need to test that first..
                 * photobase assets are copied using retouch filename
                 * non-photobase gets sent to special folder...
                 */
                if($isAthleta) {
                    continue;
                } elseif($asset->getType() == \Photobase\Asset::PHOTOBASE_ASSET) {
                    $localfile = $destination . "/" . $asset->getRetouchFilename();
                    die('is asset');
                } else {
                    $localfile = $hotfolder . "/" . $asset->getFilename();
                }
echo $localfile . "\n\n";
                continue;
                die('ok');

                $sha_match = false;
                if($localfile !== false){

                    $download_start = time();
                    $this->show_status(1, $boxfile->size);
#                    $logger->log("Downloading " . $boxfile->name . "...");

                    $data = $box->getFileContentsById($boxfile->id);

                    // @TODO -- check against the sha-1

                    $download_time = 1 + time() - $download_start;
                    $throughput = round( (strlen($data) / $download_time) / 1024, 3);
                    $logger->log("Done! $download_time sec (~$throughput KB/sec)" . PHP_EOL,
                        array(
                            "path" => $source,
                            "Direction" => "IN",
                            "status" => "SUCCESS",

                        )
                    );

                    $logger->log("Saving to $localfile...");
                    file_put_contents($localfile, $data, FILE_BINARY);
                    $logger->log("Done." . PHP_EOL,
                        array(
                            "path" => $source,
                            "Direction" => "IN",
                            "status" => "SUCCESS",
                            )
                        );

                    // verify SHA-1 after saving to disk
                    $sha_match = sha1_file($localfile) == $boxfile->sha1;
                }


                if($sha_match || $isAthleta){

                    $logExtraOptions = array(
                        "path" => $MRCLIPPING_FROM_SOURCE,
                        "Direction" => "IN",
                        "status" => "SUCCESS",

                        "event" => "transfer",
                        "source" => $remote_path,
                        "destination" => $destination,
                        "duration" => @$download_time,

                    );

                    if($isAthleta)
                    {
                        $logExtraOptions["event"] = "archive";
                        $logExtraOptions["source"] = $remote_path;
                        $logExtraOptions["destination"] = $archive;
                        $logExtraOptions["duration"] = "0";
                    }

                    $logger->log("Archiving $source/" . $boxfile->name . " to  $archive");

        // @TODO - make this more opaque $box->setFileInformation($file,$info)
                    $archiveFolder = $box->getFolderByName($archive);
                    $box->updateFileParentID($boxfile, $archiveFolder->id);
                    $logger->log("Done." . PHP_EOL,
                        $logExtraOptions
                    );
                }
            } // end foreach ($folder->item_collection->entries as $boxfile)
        }// end if ($folder->item_collection->total_count > 0 > 0)
    }


/*
--- Status Bar

Copyright (c) 2010, dealnews.com, Inc.
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

 * Redistributions of source code must retain the above copyright notice,
   this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the distribution.
 * Neither the name of dealnews.com, Inc. nor the names of its contributors
   may be used to endorse or promote products derived from this software
   without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

 */

/**
 * show a status bar in the console
 *
 * <code>
 * for($x=1;$x<=100;$x++){
 *
 *     show_status($x, 100);
 *
 *     usleep(100000);
 *
 * }
 * </code>
 *
 * @param   int     $done   how many items are completed
 * @param   int     $total  how many items are to be done total
 * @param   int     $size   optional size of the status bar
 * @return  void
 *
 */

    function show_status($done, $total, $size=30) {

        static $start_time;

        // if we go over our bound, just ignore it
        if($done > $total) return;

        if(empty($start_time)) $start_time=time();
        $now = time();

        $perc=(double)($done/$total);

        $bar=floor($perc*$size);

        $status_bar="\r[";
        $status_bar.=str_repeat("=", $bar);
        if($bar<$size){
            $status_bar.=">";
            $status_bar.=str_repeat(" ", $size-$bar);
        } else {
            $status_bar.="=";
        }

        $disp=number_format($perc*100, 0);

        $status_bar.="] $disp%  $done/$total";

        $rate = ($now-$start_time)/$done;
        $left = $total - $done;
        $eta = round($rate * $left, 2);

        $elapsed = $now - $start_time;

        $status_bar.= " remaining: ".number_format($eta)." sec.  elapsed: ".number_format($elapsed)." sec.";

        echo "$status_bar  ";

        flush();

        // when done, send a newline
        if($done == $total) {
            echo "\n";
        }

    }

}
