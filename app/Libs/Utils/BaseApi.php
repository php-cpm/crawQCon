<?php
namespace App\Libs\Utils;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 *
 *
 * User: zouyi
 * Date: 2016-04-21 14:50
 */
class BaseApi
{
    protected $client;
    public $response;
    public $params;
    public $proxy = false;
    public $proxyList = [
    ];

    public function __construct()
    {
        if ($this->proxy) {
            $this->params['proxy'] = $this->proxyList;
        }
    }

    public function get($url, $params = [], $options = [])
    {
        try{

            $this->client = new \GuzzleHttp\Client($options);
            $this->params['query'] = $params;
            $this->response = $this->client->get($url, $this->params);

            $content = $this->response->getBody()->getContents();
            if($content){
                return $content;
            }else{
                Log::addInfo('try once');
                $this->response = $this->client->get($url, $this->params);
                $content = $this->response->getBody()->getContents();
                return $content;
            }
        }catch (\Exception $e)
        {
            throw new \Exception('', 500,$e);
            return null;
        }
    }

    public function multi($urls, $params = [], $options = []){

        $this->client = new \GuzzleHttp\Client($options);
        $sendUrls = [];
        foreach($urls as $url){
            $sendUrls[] = $this->client->get($url,$this->params);
            echo $url;
        }
        try {

            foreach($sendUrls as $url){
                $data[] = $url->getBody()->getContents();
            }
            return $data;
        } catch (ClientException $e) {

            echo "The following exceptions were encountered:\n";
            foreach ($e as $exception) {
                echo $exception->getMessage() . "\n";
            }

            echo "The following requests failed:\n";
            foreach ($e->getFailedRequests() as $request) {
                echo $request . "\n\n";
            }

            echo "The following requests succeeded:\n";
            foreach ($e->getSuccessfulRequests() as $request) {
                echo $request . "\n\n";
            }
        }
    }
}