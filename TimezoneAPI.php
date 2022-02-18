<?php

Class TimezoneAPI {
    private $key = false; // API-key
    private $format = 'json'; // json/xml
    private $sleep = 0;

    public function __construct(array $params) {
        if(!empty($params['key'])) {
            $this->key = $params['key'];
        }
        if(!empty($params['format'])) {
            $this->format = $params['format'];
        }
        if(!empty($params['sleep'])) {
            $this->sleep = $params['sleep'];
        }
    }

    /**
     * Запрашиваем данные о городе по координатам
     * params float $lat, float $lng - координаты
     * params string $time - время UTC, для которого вернется локальное время
     * return object $response - ответ json в формате объекта
     * return bool false - в случае проблем
     *
     */
    public function getTimeZoneByPosition(float $lat, float $lng, string $time='')
    {
        $query_data = [
            'url' => 'http://api.timezonedb.com/v2.1/get-time-zone',
            'params' => [
                'by' => 'position',
                'lat' => $lat,
                'lng' => $lng,
            ],
        ];

        if(!empty($time)) {
            $query_data['params']['time'] = strtotime($time);
        }

        $response = $this->query($query_data);
        if($response === false) {
            return false;
        }

        return $response;
    }

    /**
     * Запрашиваем список доступных городов по названию страны, зоны
     * params string $country_name - страна
     * params string $zone_name - зона
     * return object $zones - ответ json в формате объекта
     * return bool false - в случае проблем
     *
     */
    protected function getListTimeZone(string $country_name='', string $zone_name='')
    {
        $query_data = [
            'url' => 'http://api.timezonedb.com/v2.1/list-time-zone',
            'params' => [
                'country' => $country_name,
                'zone' => $zone_name,
                'fields' => 'countryCode,countryName,zoneName,gmtOffset,dst,timestamp'
            ],
        ];

        $response = $this->query($query_data);
        if($response === false) {
            return false;
        }

        return $response->zones;
    }

    /**
     * Формирует и выполняет запрос к АПИ
     * params array $query_data - параметры запроса
     * return object $return - результат ответа в формате json объекта
     * return bool false - в случае проблем
     *
     */
    protected function query($query_data)
    {
        sleep($this->sleep); // сделаем паузу в 1 секунду
        $query_data['params'] = array_merge(
            isset($query_data['params'])?$query_data['params']:[],
            ['key' => $this->key, 'format' => $this->format]
        );
        $url_opt = $query_data['url'] . "?" . http_build_query($query_data['params']);

        $ch = curl_init();
        $options = array(
            CURLOPT_URL => $url_opt,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 120,
            CURLOPT_TIMEOUT        => 120,
        );

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        curl_close($ch);
        $return = json_decode($response);

        if(!is_object($return) || $return->status === 'FAILED') {
            /*$error_msg = "<b>" . __METHOD__ . ":</b> ";
            if(!is_object($return)) {
                $error_msg .= "unexpected response data from TimeZoneAPI!<br>";
            }
            if($return->status === 'FAILED') {
                $error_msg .= $return->message . "<br>";
            }
            $error_msg .= "<b>Query data:</b><br>";
            $error_msg .= "<pre>" . var_export($query_data, true) . "</pre>";
            echo $error_msg;*/
            return false;
        }

        return $return;
    }
}
