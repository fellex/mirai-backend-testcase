<?php

Class Base {
    private $routers = [ // доступные действия
        'get_local_time',
        'get_utc_time',
        'update_timezones',
    ];
    private $MySQL; // доступ к БД
    private $TimezoneAPI; // доступ к АПИ
    protected $future_interval;

    public function __construct($config)
    {
        $this->future_interval = $config['future_interval']; // будем извлекать данные на 3 месяца вперед
        $this->MySQL = new MySQL($config['mysql']);
        $this->TimezoneAPI = new TimezoneAPI($config['timezone_api']);
    }

    public function run()
    {
        header('Content-Type: application/json; charset=utf-8'); // возвращаем JSON
        $json_data = [ // дефолтные данные
            'status' => 'ok', // ok/error
            'message' => '', // сообщение об ошибке
        ];
        if(!isset($_GET['act']) || !in_array($_GET['act'], $this->routers)) { // если запрос несуществующих действий
            header("HTTP/1.1 404 Not Found");
            $json_data['status'] = 'error';
            $json_data['message'] = '404 Not Found';
        } else {
            switch($_GET['act']) {
                case 'get_local_time':
                    $this->getLocalTime($json_data);
                    break;
                case 'get_utc_time':
                    $this->getUtcTime($json_data);
                    break;
                case 'update_timezones':
                    $this->updateTimezones($json_data);
                    break;
            }
        }
        echo json_encode($json_data);
    }

    /**
     * Получает локальное время по метке UTC для конкретного города
     * params array $json_data - ссылка на возвращаемые данные для заполнения
     *
     */
    private function getLocalTime(&$json_data)
    {
        if(empty($_GET['city'])) {
            $json_data['status'] = 'error';
            $json_data['message'] = 'Empty city';
            return;
        }
        $city_id = $_GET['city'];
        if(empty($_GET['utc_time'])) {
            $json_data['status'] = 'error';
            $json_data['message'] = 'Empty utc time';
            return;
        }
        $utc_time = $_GET['utc_time'];

        $city = $this->MySQL->getCity($city_id);

        $utc_date = date("Y-m-d H:i:s", $utc_time);

        if($utc_date < $city['border_ts']) { // если UTC-метка до границы
            $local_date = date("Y-m-d H:i:s", ($utc_time + $city['before']));
        } else {
            $local_date = date("Y-m-d H:i:s", ($utc_time + $city['after']));
        }

        $json_data['data'] = [
            'utc_date' => $utc_date,
            'local_date' => $local_date,
        ];
    }

    /**
     * Получает UTC время по локальному времени для конкретного города
     * params array $json_data - ссылка на возвращаемые данные для заполнения
     *
     */
    private function getUtcTime(&$json_data)
    {
        if(empty($_GET['city'])) {
            $json_data['status'] = 'error';
            $json_data['message'] = 'Empty city';
            return;
        }
        $city_id = $_GET['city'];
        if(empty($_GET['local_time'])) {
            $json_data['status'] = 'error';
            $json_data['message'] = 'Empty local time';
            return;
        }
        $local_time = $_GET['local_time'];

        $city = $this->MySQL->getCity($city_id);

        $local_date = date("Y-m-d H:i:s", $local_time);

        if(date("Y-m-d H:i:s", ($local_time - $city['after'])) >= $city['border_ts']) {
            $utc_date = date("Y-m-d H:i:s", ($local_time - $city['after']));
        } else {
            $utc_date = date("Y-m-d H:i:s", ($local_time - $city['before']));
        }

        $json_data['data'] = [
            'utc_date' => $utc_date,
            'local_date' => $local_date,
        ];
    }

    /**
     * Обновляет данные о смене часовых поясов на ближайший интервал "в будущее" по всем городам в БД
     * params array $json_data - ссылка на возвращаемые данные для заполнения
     *
     */
    private function updateTimezones(&$json_data)
    {
        $date_start = date("Y-m-d H:00:00"); // сейчас по Гринвичу
        $date_end = date("Y-m-d H:i:s", strtotime($date_start . "+" . $this->future_interval . " MONTH")); // сейчас + интервал "будущего" из конфига

        $db_list_timezone = $this->MySQL->getListCities();
        foreach($db_list_timezone as $db_timezone) {
            $db_timezone['change_timezone'] = [
                'border_ts' => null,
                'before' => null,
                'after' => null,
            ];
            $this->findBorderTS($db_timezone, $date_start, $date_end); // пытаемся определить смену часовых поясов с точностью до часа
            $this->MySQL->saveChangeTimezone(array_merge($db_timezone['change_timezone'], ['id' => $db_timezone['id']]));
        }

        $json_data['message'] = "Updated " . count($db_list_timezone) . " cities";
    }

    /**
     * Определяет временную границу смену часовых поясов на указанном интервале в рекурсии
     * params array $db_timezone - ссылка на массив с данными для заполнения
     * params string $date_start - начало интервала
     * params string $date_end - конец интервала
     * params string $current_date - текущая дата в этой итерации
     * params string $interval - интервал, на который будет сделан сдвиг даты в целях поиска границы
     * params string $previous - значение gmtOffset на предыдущем шаги итерации
     * params bool $search - флаг, что смена часовых поясов обнаружена, идет уточнение часа
     *
     */
    private function findBorderTS(&$db_timezone, $date_start, $date_end, $current_date='', $interval='+2 WEEK', $previous=null, $search=false)
    {
        preg_match(
            '/^(\+|-)([0-9]{1,2} [A-Z]*)$/',
            $interval,
            $matches
        );
        $interval_sign = $matches[1]; // положительное/отрицательное смещение часовых поясов
        $interval_type = $matches[2]; // варианты интервалов, указаны ниже

        // смена смещения на следующем шаге рекурсии
        $change_interval = function() use($interval_type, $interval_sign) {
            switch($interval_type) {
                case '2 WEEK':
                    $interval_type = '7 DAYS';
                    break;
                case '7 DAYS':
                    $interval_type = '2 DAYS';
                    break;
                case '2 DAYS':
                    $interval_type = '12 HOURS';
                    break;
                case '12 HOURS':
                    $interval_type = '4 HOURS';
                    break;
                case '4 HOURS':
                    $interval_type = '1 HOURS';
                    break;
            }
            $interval_sign = ($interval_sign == '+')?'-':'+';

            return $interval_sign . $interval_type;
        };

        if(is_null($db_timezone['change_timezone']['before'])) { // первая итерация
            $current_timezone = $this->TimezoneAPI->getTimeZoneByPosition($db_timezone['latitude'], $db_timezone['longitude'], $date_start);
            $db_timezone['change_timezone']['before'] = $previous = $current_timezone->gmtOffset;
            $current_date = $date_start;
            $this->findBorderTS($db_timezone, $date_start, $date_end, $current_date, $interval, $previous, $search);
        } else {
            $current_date = date("Y-m-d H:i:s", strtotime($current_date . $interval));
            if($current_date <= $date_end) { // еще не дошли до конца
                // запрос в АПИ
                $current_timezone = $this->TimezoneAPI->getTimeZoneByPosition($db_timezone['latitude'], $db_timezone['longitude'], $current_date);

                if($previous == $current_timezone->gmtOffset) { // нет смены часовых поясов
                    $this->findBorderTS($db_timezone, $date_start, $date_end, $current_date, $interval, $previous, $search);
                } else { // есть смена часовых поясов
                    $search = true;

                    if($interval_type == '1 HOURS') { // смена часовых поясов обнаружена при смещении на 1 час, значит это именно тот час

                        /*if(
                            !is_null($db_timezone['change_timezone']['before'])
                            && !is_null($db_timezone['change_timezone']['after'])
                            && !is_null($db_timezone['change_timezone']['border_ts'])
                        ) {
                            print('Двойная смена за указанный интервал');
                        }*/

                        if($interval_sign == '-') { // если двигались назад
                            $db_timezone['change_timezone']['border_ts'] = date("Y-m-d H:i:s", strtotime($current_date . "+1 HOUR"));
                            $db_timezone['change_timezone']['after'] = $previous;
                            $current_date = date("Y-m-d H:i:s", strtotime($current_date . "+1 HOUR"));
                        } else { // если вперед
                            $db_timezone['change_timezone']['border_ts'] = date("Y-m-d H:i:s", strtotime($current_date));
                            $db_timezone['change_timezone']['after'] = $current_timezone->gmtOffset;
                        }
                        // сбрасываем параметры, чтобы пройти до конца интервала после найденной точки смены часовых поясов
                        // на тот случай, если в интервале будет больше одной смены часовых поясов
                        // реализовывать этот момент не буду, но вобщих чертах расширить таблицу в БД и дописывать данные
                        $search = false;
                        $interval='+2 WEEK';
                        $previous = $db_timezone['change_timezone']['after'];
                    } else {
                        $previous = $current_timezone->gmtOffset;
                        $interval = $change_interval();
                    }

                    $this->findBorderTS($db_timezone, $date_start, $date_end, $current_date, $interval, $previous, $search);
                }
            }
        }
    }
}
