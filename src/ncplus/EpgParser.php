<?php

namespace ncplus;

//use parser\EpgException;


class EpgParser
{

    /**
     *   ToDo:
     *   убрать http://develops.online/proxy.php?, если не использовать прокси лист
     */
    protected $config = array(
        'baseUrl' => 'http://develops.online/proxy.php?http://ncplus.pl/',
        'curlProxy' => false,//false or ip
        'curlTor' => false,
        'curlTorPort' => null //set if curlTor is true(default 9050)
    );
    protected $curlOptions = array();
    protected $userCurlOptions = array();
    protected $curlError = null;
    protected $curlResult = null;
    protected $curlInfo = array();
    protected $errors = array();
    protected $curlObject = null;
    protected $debug = false;
    protected $headers;


    public function __construct($config = array())
    {
        if ($config) {
            foreach ($config as $key => $value) {
                $this->config[$key] = $value;
            }
        }
        $this->headers = array(
            'Accept: text/html,application/xhtml+xml,application/xml,application/json;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Accept-Encoding: gzip, deflate',
            'Connection: Keep-Alive',
            'Cache-Control: max-age=0',
            'Content-type: application/x-www-form-urlencoded;charset=UTF-8'
        );
    }

    /**
     * @param mixed $key
     * @param mixed $val
     * @return $this
     */
    public function setCurlOption($key, $val)
    {
        $this->userCurlOptions[$key] = $val;
        return $this;
    }

    /**
     *
     * @param string $day as Y-m-d
     * @return array|boolean
     */
    public function loadDay($day)
    {
        try {
            $dayObject = new \DateTime($day);
        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }

        $url = $this->config['baseUrl'] . "~/epgjson/" . $dayObject->format('Y-m-d') . ".ejson";

        $this->initCurl($url)->runCurl();

        if ($this->curlError) {
            $error = $this->curlError . "\n Url: " . $url . ";";
            if (isset($this->curlOptions[CURLOPT_PROXY])) {
                $error .= "\n Proxy: " . $this->curlOptions[CURLOPT_PROXY];
            }
            $this->setError($error);
            return false;
        }

//        if (strpos("400 Bad Request", $this->curlResult))
//            SDb::update("proxy", ["bad_proxy" => true], "proxy=?", $proxy);

        if ($this->curlInfo['http_code'] != '200' || strpos($this->curlInfo['content_type'], 'application/json') === false) {
            $this->setError("http code is not OK or content is invalid " . $this->curlInfo['http_code'] . "/" . $this->curlInfo['content_type']);
            return false;
        }


        return json_decode($this->curlResult);
    }


    /**
     * @param string $url
     * @return $this
     */
    protected function initCurl($url)
    {
        $this->resetCurl();


        $this->curlOptions = $this->userCurlOptions;
        $this->curlOptions[CURLOPT_USERAGENT] = "Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36";
        $this->curlOptions[CURLOPT_TIMEOUT] = 180;
        $this->curlOptions[CURLOPT_HTTPHEADER] = $this->headers;
        $this->curlOptions[CURLOPT_ENCODING ] = "gzip";
        //$this->curlOptions[CURLOPT_HEADER] = 1;
        if ($this->debug) {
            $f = fopen("curl.log", "a");
            $this->curlOptions[CURLOPT_VERBOSE] = true;
            $this->curlOptions[CURLOPT_STDERR] = $f;
        }


        $cookie = "_hjIncludedInSample=1; __cfduid=d11ec92f38ac181739b1236ee476ad2641505809906; telecast_favourites_on_startup=0; __gfp_64b=WuplQiXsYKUCwVy._L4go.LBVu_HP9I8CX3p8P6ZaD..g7; smsessioncount=1; smsession=1505810223348; __utmz=215593480.1505810225.1.1.utmccn=(direct)|utmcsr=(direct)|utmcmd=(none); smsource=direct / none; historyCookie=%7B%221%22%3A%22http%3A%2F%2Fncplus.pl%2Fsklep%2Foferta%2Fvip-max%22%7D; __lc.visitor_id.8930889=S1506594659.c2d4da6161; lc_window_state=minimized; frosmo_quickContext=%7B%22VERSION%22%3A%221.1.0%22%2C%22UID%22%3A%22zgst8q.j7rccdwi%22%2C%22lastPageView%22%3A%7B%22time%22%3A1506594665717%7D%2C%22states%22%3A%7B%22session%22%3A%7B%7D%7D%7D; frosmo_keywords=.; autoinvite_callback=true; __gads=ID=30f852853e99f612:T=1506594818:S=ALNI_MbBO36oFQMQ6fGZt8285Dltp2uf9A; ncplus#lang=pl-PL; __sonar=3173564862404649912; smvr=eyJ2aXNpdHMiOjEsInZpZXdzIjo3LCJ0cyI6MTUwNjU5NzM3NzY1MiwibnVtYmVyT2ZSZWplY3Rpb25CdXR0b25DbGljayI6MCwiaXNOZXdTZXNzaW9uIjpmYWxzZX0=; smuuid=15ec80c8fa8-43301df50700-a9e49a48-6e16cdb5-edf760a8-8fde1bf742e1; __utma=215593480.796021043.1505810225.1506594941.1506601302.3; __utmc=215593480; _ga=GA1.2.87282278.1505810222; __goadservices=3-xw9o_rf6FQFiqLyWAJJsrz0u5k5IoEaTD8Bw3XVA7HU";
        $this->curlOptions[CURLOPT_COOKIEJAR] = $cookie;
        $this->curlOptions[CURLOPT_COOKIE] = $cookie;
        $this->curlOptions[CURLOPT_FOLLOWLOCATION] = 1;
        $this->curlOptions[CURLOPT_RETURNTRANSFER] = 1;

        foreach ($this->userCurlOptions as $key => $val) {
            $this->curlOptions[$key] = $val;
        }

        /**
         *   ToDo:
         *   убрать восклицательный, если использовать прокси лист
         */
        $this->curlOptions[CURLOPT_URL] = $url;
        if (!$this->config['curlProxy']) {
            $this->curlOptions[CURLOPT_PROXY] = $this->config['curlProxy'];
        } elseif ($this->config['curlTor']) {
            $this->setCurlTor();
        }

        if (!$this->curlObject) {
            $this->curlObject = curl_init($url);
        }
        return $this;
    }

    /**
     * @return $this
     */
    protected function resetCurl()
    {
        $this->curlObject = null;
        $this->curlOptions = array();
        $this->curlInfo = array();
        $this->curlResult = null;
        return $this;
    }

    /**
     * @return $this
     */
    protected function runCurl()
    {
        try {
            curl_setopt_array($this->curlObject, $this->curlOptions);
            $this->curlResult = curl_exec($this->curlObject);

            $this->curlError = curl_error($this->curlObject);
            $this->curlInfo = curl_getinfo($this->curlObject);

        } catch (\Exception $e) {
            $this->setError($e->getMessage());
            $this->curlError = $e->getMessage();
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function setCurlTor()
    {
        $this->curlOptions[CURLOPT_AUTOREFERER] = 1;
        $this->curlOptions[CURLOPT_RETURNTRANSFER] = 1;
        $this->curlOptions[CURLOPT_PROXY] = '127.0.0.1:' . ($this->config['curlTorPort'] ? (int)$this->config['curlTorPort'] : 9050);
        $this->curlOptions[CURLOPT_PROXYTYPE] = 7;
        $this->curlOptions[CURLOPT_TIMEOUT] = 120;
        $this->curlOptions[CURLOPT_VERBOSE] = 0;
        $this->curlOptions[CURLOPT_HEADER] = 0;
        return $this;
    }

    /**
     * @param $error
     * @return $this
     */
    public function setError($error)
    {
        $this->errors[] = $error;
        return $this;
    }

    /**
     * @return array
     */
    public function getCurlInfo()
    {
        return $this->curlInfo;
    }

    /**
     *
     * @param array $json
     * @return boolean|array
     */
    public function parseCommonData($json = array())
    {
        if (!is_array($json)) {
            $this->setError("Invalid input");
            return false;
        }

        $channels = array();
        $programs = array();
        foreach ($json as $id => $chInfo) {
            $channels[$chInfo[0]] = trim($chInfo[1]);
            $programs[$chInfo[0]] = $chInfo[2];
        }
        $programsFinal = array();

        foreach ($programs as $chan => $pr) {
            if (!isset($pr[0])) {
                continue;
            }
            if (!isset($programsFinal[$chan])) {
                $programsFinal[$chan] = array();
            }
            foreach ($pr as $prg) {
                $airTimestamp = $prg[2];
                try {
                    $date = new \DateTime(date('Y-m-d H:i:s', $prg[2]));
                } catch (\Exception $ex) {
                    $this->setError($ex->getMessage());
                    continue;
                }
                $programsFinal[$chan][] = array(
                    'id' => $prg[0],
                    'name' => $prg[1],
                    'airDate' => $date->format('Y-m-d'),
                    'airTime' => $date->format('H:i:s'),
                    'airLength' => $prg[3],
                    'idChannel' => $chan
                );
            }
        }

        return array('channels' => $channels, 'programs' => $programsFinal);
    }

    /**
     *
     * @param int $id
     * @return boolean|array
     */
    public function getProgramInfo($id)
    {
        $url = $this->config['baseUrl'] . "program-tv?rm=ajax&id={$id}&v=5";
        $this->initCurl($url);
        $this->setCurlOption(CURLOPT_HTTPHEADER, $this->headers);

        $this->runCurl();
        if ($this->curlError) {
            $error = $this->curlError . "\n Url: " . $url . ";";
            if (isset($this->curlOptions[CURLOPT_PROXY])) {
                $error .= "\n Proxy: " . $this->curlOptions[CURLOPT_PROXY];
            }
            $this->setError($error);
            return false;
        }
        if ($this->curlInfo['http_code'] != '200' || strpos($this->curlInfo['content_type'], 'application/json') === false) {
            $proxyPart = (isset($this->curlOptions[CURLOPT_PROXY]) ? "; proxy: " . $this->curlOptions[CURLOPT_PROXY] : "");
            $this->setError("Http code is not OK or content is invalid " . $this->curlInfo['http_code'] . "/" . $this->curlInfo['content_type'] . " for url " . $url . $proxyPart);
            return false;
        }

        return json_decode($this->curlResult);
    }

    /**
     * Handle program data and get rid of unnecessary data
     * @param array $json - result of getProgramInfo
     * @return boolean|array with fields descr,urlNcpluspl,category,country,movieCast,movieDirector
     */
    public function parseProgramData($json = array())
    {
        if (!is_array($json)) {
            $this->setError("Invalid input");
            return false;
        }
        $program = array(
            'descr' => (isset($json[0]) && $json[0]) ? $json[0] : null,
            'urlNcpluspl' => (isset($json[1]) && $json[1]) ? $json[1] : null, //eg 11006845-teletoon-gry-3-odc-10-teletoon-hd-20150417-0915 - можно вытащить канал
            'category' => (isset($json[3]) && $json[3]) ? $json[3] : null,
            'country' => (isset($json[4]) && $json[4]) ? $json[4] : null,
            'movieCast' => (isset($json[5]) && $json[5]) ? $json[5] : null, //в ролях
            'movieDirector' => (isset($json[7]) && $json[7]) ? $json[7] : null,
        );
        return $program;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return mixed|null
     */
    public function getLastError()
    {
        if ($this->errors) {
            return end($this->errors);
        }
        return null;
    }

}