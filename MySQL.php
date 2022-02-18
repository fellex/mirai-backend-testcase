<?php

class MySQL {
    private $pdo;

    // ����������� � ��
    public function __construct($config)
    {
        if(empty($config['host'])) {
            throw new Exception("MySQL connection failed: empty host name");
        }
        if(empty($config['dbname'])) {
            throw new Exception("MySQL connection failed: empty database name");
        }
        if(empty($config['user'])) {
            throw new Exception("MySQL connection failed: empty user name");
        }
        /*if(empty($config['password'])) {
            throw new Exception("MySQL connection failed: empty password");
        }*/

        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_COMPRESS => true,
            ];
            $this->pdo = new PDO("mysql:charset=utf8mb4;host=" . $config['host'] . ";dbname=" . $config['dbname'], $config['user'], $config['password'], $options);
        } catch(PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }

    /**
     * �������������� � ��������� SQL-������
     * params string $sql - SQL-������
     * $params array $params - ��������� SQL-�������
     *
     */
    public function doQuery($sql, $params = array())
    {
        $e = null;
        if(empty($sql)) {
            throw new Exception(__METHOD__ . '() ������ ��� ���������� SQL-�������: ������� ������ $sql SQL-���!');
        }
        if(!is_array($params)) {
            throw new Exception(__METHOD__ . '() ������ ��� ���������� SQL-�������: ��������� $params ������ ������������ � ���� �������!');
        }

        $stm = $this->pdo->prepare($sql);
        try {
            foreach($params as $k => $v) {
                if(is_int($v)) {
                    $stm->bindValue($k, $v, PDO::PARAM_INT);
                } else {
                    $stm->bindValue($k, $v, PDO::PARAM_STR);
                }
            }
            $res = $stm->execute();

            if(!$stm instanceof PDOStatement || $res === false) { // ���� ������ �� ��������
                throw new Exception(__METHOD__ . "() �������������� ������!");
            }
            return $stm;
        } catch (Throwable $e) {

        } catch (Exception $e) {

        }

        if(!is_null($e)) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * �������� ����� �� ID
     * params string $id - ID ������
     * return array - ������ �� ��
     *
     */
    public function getCity(string $id)
    {
        $sql = "SELECT `border_ts`, `before`, `after` FROM `change_timezone` WHERE `id` = :id;";
        $stm = $this->doQuery($sql, ['id' => $id]);
        return $stm->fetch();
    }

    /**
     * �������� ��� ������ �� ��
     * return array - ������ �� ��
     *
     */
    public function getListCities()
    {
        $sql = "SELECT * FROM `city`;";
        $stm = $this->doQuery($sql);
        return $stm->fetchAll();
    }

    /**
     * ��������� ������ � ����� �������� ����� � ������
     * params array $data - ������ ��� ����������
     * return bool true/false - ��������� ����������
     *
     */
    public function saveChangeTimezone(array $data)
    {
        $sql = "INSERT INTO `change_timezone` (`id`, `border_ts`, `before`, `after`) VALUES (:id, :border_ts, :before, :after)
                ON DUPLICATE KEY UPDATE `border_ts` = :border_ts, `before` = :before, `after` = :after;";
        $params = [
            'id' => '',
            'border_ts' => '',
            'before' => '',
            'after' => '',
        ];
        foreach($params as $k => &$v) {
            if(!array_key_exists($k, $data)) {
                return false;
            }
            $v = $data[$k];
        }

        $this->doQuery($sql, $params);
        return true;
    }
}
