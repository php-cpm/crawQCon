<?php

namespace App\Console\Commands;

use Guzzle\Common\Exception\MultiTransferException;

use App\Libs\Utils\BaseApi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use QL\QueryList;

class Crawler extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawler';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'crawl web page & save';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->qcon();
    }


    public function qcon()
    {
        $api = new BaseApi();
        $html = Storage::disk('local')->get('sites/qconbj2016.txt');
        if (!$html) {

            $url = 'http://ppt.geekbang.org/qconbj2016';
            $data = $api->get($url);
            Storage::disk('local')->put('sites/qconbj2016.txt', $data);
            $html = $data;
        }

        $rules = [
            'title' => ['.download a:even', 'text'],
            'url' => ['.download a:odd', 'href'],
        ];
        $datas = QueryList::Query($html, $rules)->data;
        $this->comment(PHP_EOL . 'qcon' . PHP_EOL);
        $this->qconpdfDownload($datas);
    }

    public function qconpdfDownload($urls)
    {

        $data = null;
        $newUrls = [];
        foreach ($urls as $data) {
            $url = $data['url'];
            $k = md5($url);
            $title = $data['title'];
            $newUrls[$k] = $title;

            if(Storage::disk('local')->exists('file/' . $title.'.pdf')){
                $this->comment('continue ' . $title . PHP_EOL);
                continue;
            }
            $baseUrl = 'http://ppt.geekbang.org';
            $title = str_replace('/', ' ', $title);
            $title = str_replace('\\', ' ', $title);
            $title = str_replace('?', ' ', $title);
            $title = str_replace('-', ' ', $title);

            $this->comment('saving ' . $title . PHP_EOL);

            $response = $this->saveFile($baseUrl . $url);

            $this->comment('execSave ' . PHP_EOL);

            if($response){

                $this->execSave($response, $newUrls[$k], '.pdf');
            }else{

                $this->comment('failed request '.$newUrls[$k] . PHP_EOL);
            }

        }


    }


    public function getOptions()
    {

        $headers = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, sdch',
            'Accept-Language' => 'zh-CN,zh;q=0.8,en;q=0.6',
            'Connection' => 'keep-alive',
            'Cookie' => 'dx_un=%E9%82%B9%E6%AF%85%F0%9F%8E%B8; dx_avatar=http%3A%2F%2F7xil0e.com1.z0.glb.clouddn.com%2Fuser_571da518be955.png; dx_token=27f757a9fe6a7b710c57cfb15a6cfbe3; Hm_lvt_d1ede189ce3a27a3412fe7ed21ccbe71=1461560577; Hm_lpvt_d1ede189ce3a27a3412fe7ed21ccbe71=1461587623',
            'Host' => 'ppt.geekbang.org',
            'Upgrade-Insecure-Requests' => '1',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.93 Safari/537.36',

        ];
        $option = [
            'headers' => $headers,
            'timeout' => 60,
            'connect_timeout' => 2,
        ];
        return $option;
    }

    public function saveFile($url)
    {
        try{

            $option = $this->getOptions();
            $client = new \GuzzleHttp\Client();
            $response = $client->get($url, $option);
            return $response;
        }catch (\Exception $e)
        {
            return null;
        }

    }

    public function execSave($response, $name, $extension)
    {
        $header = $response->getHeaders();
        print_r($header);

        $name = $name . $extension;
        $this->comment($name . PHP_EOL);
        if ($header['Content-Length'] > 4500 ) {

            $result = ($response->getBody()->getContents());

            Storage::disk('local')->put('file/' . $name, $result);
        }else{

            $this->comment('skip' . PHP_EOL);
        }

    }

    public function queryArticle($urls)
    {
        $api = new BaseApi();
        $article = [];
        foreach ($urls as $url) {
            $this->comment($url . PHP_EOL);
            $result = $api->get($url);
            $after = $this->parse($result, $url);
            if (!$after) {
                $this->comment($url . PHP_EOL);
                $result = $api->get($url);
                $after = $this->parse($result, $url);
            }
            Storage::disk('local')->put('data/' . md5($url) . '.txt', $after);
            $data[] = $result;
            $article[] = $after;
        }
    }

    public function parse($data, $url)
    {
        $rules = [
            'title' => ['article.post>header>h1', 'text'],
            'date' => ['date.post-meta', 'text'],
            'article' => ['div.post-content', 'html'],
        ];
        $urls = QueryList::Query($data, $rules);
        $result = $urls->data;
        if (!empty($result)) {
            $result = $result[0];
            $result['url'] = $url;
            $result['date'] = date('Y-m-d', strtotime($result['date']));
            return $result;
        } else {
            return null;
        }
    }
}
