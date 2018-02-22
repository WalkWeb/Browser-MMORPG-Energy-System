<?php

class EnergyModel
{
    /**
     * @var \mysqli
     */
    public $DB;

    /**
     * Подключение к БД
     *
     * Не забудьте указать свои данные для подключения к MySQL!
     */
    public function __construct()
    {
        $this->DB = mysqli_connect('localhost', 'db_user', 'db_password', 'db_name')
        or die('Невозможно подключиться к серверу БД.');
        $this->DB->query('SET NAMES utf8');
    }

    /**
     * Возвращает массив  данных пользователя
     *
     * @param $id int
     * @return array
     */
    public function getUserInfo($id)
    {
        $user = null;

        $queryString = 'SELECT `id`, `uname`, `energy`, `energy_max`, `time`, `residue` 
                        FROM energy 
                        WHERE id = ?';
        if ($stmt = $this->DB->prepare($queryString)) {
            $stmt->bind_param(
                "i",
                $id
            );
            $stmt->execute();
            if (!$stmt->error) {
                $stmt->bind_result(
                    $user['id'],
                    $user['name'],
                    $user['energy'],
                    $user['energy_max'],
                    $user['time'],
                    $user['residue']
                );
                $stmt->fetch();
            }
            $stmt->close();
        }

         return $user;
    }

    /**
     * Записывает новое значение энергии и дополнительные параметры
     *
     * @param $id int
     * @param $value int
     * @param $time string
     * @param $residue_new string
     */
    public function editEnergy($id, $value, $time, $residue_new)
    {
        $queryString = 'UPDATE `energy` SET energy = ?, time = ?, residue = ? WHERE id = ?';
        if ($stmt = $this->DB->prepare($queryString)) {
            $stmt->bind_param(
                "issi",
                $value,
                $time,
                $residue_new,
                $id
            );
            $stmt->execute();
            if ($stmt->error) {
                echo $stmt->error;
            }
            $stmt->close();
        }
    }
}
