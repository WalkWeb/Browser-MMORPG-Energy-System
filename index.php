<?php

include('Model.php');

class Energy
{
    /**
     * ID пользователя
     *
     * @var int
     */
    private $id = 1;

    /**
     * Массив данных пользователя
     *
     * === БАЗОВЫЕ (хранятся в бд) ===
     * 1) id (int) - на всякий случай храним его и здесь, практика показывает, что это бывает полезно
     * 2) name (string) - имя
     * 3) energy (int) - текущая энергия
     * 4) energy_max (int) - максимальная энергия
     * 5) time (float) - время последнего обновления энергии
     * 6) residue (int) - остаток времени в секундах
     *
     * === ДОПОЛНИТЕЛЬНЫЕ (рассчитываются отдельно) ===
     * 7) eweight (int) - длина полоски энергии
     * 8) sweight (int) - длина полоски секунд
     * 9) time_actual (float) - текущее время
     * 10) efull (boolean) - полное ли количество энергии у пользователя
     * 11) difference (int) - разница, между временем последнего обновления энергии и текущего времени (если энергия
     *     максимальна - приравниваем данный параметр к нулю)
     * 12) addenergy - количество энергии, которую можно добавить (если энергия максимальна - приравниваем данный
     *     параметр к нулю)
     * 13) residue_new - новый остаток времени в секундах (секунд). Этот параметр нужен для более точного рассчета
     *     энергии (если энергия максимальна - приравниваем данный параметр к нулю)
     *
     * @var array
     */
    private $user;

    /**
     * Необходимое количество секунд для восстановления 1 энергии. Не ставьте это значение на 1 или меньше.
     *
     * @var int
     */
    private $cost = 20;

    /**
     * Объект работы с БД
     *
     * @var object
     */
    private $db;

    /**
     * Подключаемся к БД, делаем запрос на получение данных о пользователе, а также рассчитываем дополнительные параметры пользователя
     */
    public function __construct()
    {
        $this->db = new EnergyModel();
        $this->getBaseUserInfo();
        $this->getAddUserInfo(false);
    }

    /**
     * Получаем базовую информацию о пользователе: 1) id 2) name 3) energy 4) energy_max 5) time 6) residue
     */
    private function getBaseUserInfo()
    {
        $this->user = $this->db->getUserInfo($this->id);
    }

    /**
     * Рассчитывает дополнительные параметры пользователя на основе базовых
     *
     * @param $update bool
     */
    private function getAddUserInfo($update = true)
    {
        // Если у нас произошло обновление энергии, то базовое время и остаток секунд изменилось.
        // Мы можем обновить эти параметры дополнительным запросом в БД, а можем не делать лишний запрос и обновить вручную
        if ($update) {
            $this->user['time'] = $this->user['time_actual'];
            $this->user['residue'] = $this->user['residue_new'];
        }

        $this->user['eweight'] = round(($this->user['energy']/$this->user['energy_max']*100));

        $this->user['time_actual'] = microtime(true);
        $this->user['efull'] = ($this->user['energy'] === $this->user['energy_max']) ? true : false;

        if (!$this->user['efull']) {
            $this->user['difference'] = ($this->user['time_actual'] - $this->user['time']) + $this->user['residue'];
            $this->user['addenergy'] = floor($this->user['difference']/$this->cost);
            $this->user['residue_new'] = floor($this->user['difference'] - ($this->user['addenergy'] * $this->cost));
            $this->user['sweight'] = round(($this->user['residue_new']/$this->cost*100));
        } else {
            $this->user['difference'] = 0;
            $this->user['addenergy'] = 0;
            $this->user['residue_new'] = 0;
            $this->user['sweight'] = 0;
        }

        // Если количество добавляемой энергии больше 1 - обновляем данные в БД
        if ($this->user['addenergy'] > 0) {
            $this->editEnergy($this->user['addenergy']);
        }
    }

    /**
     * Изменяет количество энергии пользователя, а также обновляет текущие данные пользователя
     *
     * @param int $value
     * @return array
     */
    public function editEnergy($value)
    {
        // Количество энергии может быть положительным (увеличение энергии) или отрицательным (уменьшение)
        // Эти два варианта нужно обрабатывать по-разному
        if ($value > 0) {

            // Проверяем, не является ли энергия уже максимальной
            if ($this->user['energy'] === $this->user['energy_max']) {
                return array('sucsess' => 0, 'message' => 'Энергия уже максимальна');
            }

            // Увеличиваем текущую энергию
            $this->user['energy'] = $this->user['energy'] + $value;

            // Проверяем, не стала ли энергия больше максимальной, в этом случае приравниваем её к максимальной
            if ($this->user['energy'] > $this->user['energy_max']) {
                $this->user['energy'] = $this->user['energy_max'];
            }

            // Энергия изменилась, значит нужно повторно рассчитать дополнительные параметры
            $this->getAddUserInfo();

            // Записываем данные в БД
            $this->db->editEnergy($this->id, $this->user['energy'], $this->user['time_actual'], $this->user['residue_new']);

            return array('sucsess' => 1, 'message' => 'Увеличение энергии успешно');

        } elseif ($value < 0) {

            // Если энергия вычитается, то первым делом нужно узнать, хватает ли у пользователя энергии
            if (abs($value) <= $this->user['energy']) {
                $this->user['energy'] = $this->user['energy'] + $value;

                // Энергия изменилась, значит нужно повторно рассчитать дополнительные параметры
                $this->getAddUserInfo();

                // Записываем данные в БД
                $this->db->editEnergy($this->id, $this->user['energy'], $this->user['time_actual'], $this->user['residue_new']);

                return array('sucsess' => 1, 'message' => 'Уменьшение энергии успешно');
            } else {
                return array('sucsess' => 0, 'message' => 'У вас недостаточно энергии');
            }
        }

        return array('sucsess' => 0, 'message' => 'Ошибка: данная ситуация не должна была произойти (вероятно вы отправили изменение энергии на 0)');
    }

    /**
     * Выводит информацию на страницу
     *
     * @param string $message
     * @return string
     */
    public function getPage($message = '')
    {
        $fullMessage = ($this->user['efull']) ? 'Да, добавлять не нужно' : 'Нет';

        $content = '
        <html>
        <head>
            <title>Пример системы регенерации энергии в браузерной MMORPG</title>
            <meta http-equiv="content-type" content="text/html; charset=utf-8" />
            <link href="css/style.css" rel="stylesheet">
        </head>
        <body>
        <div class="user">
            <div class="ava"></div>
            <div class="name">Пользователь: '.$this->user['name'].'</div>
            <div class="energy_cont">
                <div id="energy_bar_div" class="energy_bar" style="width: '.$this->user['eweight'].'%"></div>
            </div>
            <div class="engtext">
                <span id="energy">'.$this->user['energy'].'</span>/<span id="energy_max">'.$this->user['energy_max'].'</span>
            </div>
            <div class="second_cont">
                <div id="second_bar_div" class="second_bar" style="width: '.$this->user['sweight'].'%"></div>
            </div>
            <div class="energy_text">
                <p>До получения: <span id="second">'.$this->user['residue_new'].'</span>/<span id="second_max">'.$this->cost.'</span> сек.</p>
            </div>
        </div>
        <div class="userinfo w600">
            <p class="red">'.$message.'</p>
        
            <p>Дополнительная информация ниже не обновляется динамически. Подразумевается, что на сайте будет использоваться только то,
            что отображается выше. А ниже &#151; это отладочная информация для разработчика.</p>
        </div>
        <div class="userinfo">
            <p>
                Имя: '.$this->user['name'].'<br />
                Энергии: '.$this->user['energy'].'<br />
                Максимум энергии: '.$this->user['energy_max'].'<br />
                Длина полоски энергии: '.$this->user['eweight'].'%<br />
                Длина полоски секунд: '.$this->user['sweight'].'%<br />
                Время последнего обновления: '.$this->user['time'].'<br />
                Остаток (сек.): '.$this->user['residue'].'<br /><br />
        
                Текущее время: '.$this->user['time_actual'].'<br />
                Энергия полная? '.$fullMessage.'
            </p>
        </div>
        
        <div class="formcont">
            <form method="post" action="">
                <input type="hidden" name="energy" value="meanEnergyDecrease">
                <button>-30 энергии</button>
            </form>
            <form method="post" action="">
                <input type="hidden" name="energy" value="meanEnergyIncrease">
                <button>+30 энергии</button>
            </form>
        </div>
        
        <script>
            // Подготавливаем параметры для таймера
            var interval = 1000; // Время одной секунды (код подразумевает, что это будут секунды, хотя это может быть и другая величина)
            var expected = Date.now() + interval;
            var energy = '.$this->user['energy'].'; // Текущее количество энергии
            var energy_max = '.$this->user['energy_max'].'; // Максимальное количество энергии
            var second = '.$this->user['residue_new'].'; // Текущее количество секунд
            var second_max = '.$this->cost.'; // Требуемое количество секунд для получения 1 энергии
            var energy_bar;        // Размер полоски энергии
            var second_bar;        // Размер полоски секунд
        </script>
        <script src="js/energy.js"></script>
        </body>
        </html>';

        return $content;
    }
}

$page = new Energy();
$result = null;

if ($_POST) {
    if       ($_POST['energy'] === 'meanEnergyDecrease') {
        $result = $page->editEnergy(-30);
    } elseif ($_POST['energy'] === 'meanEnergyIncrease') {
        $result = $page->editEnergy(30);
    } else {
        $result = array('sucsess' => 0, 'message' => 'Вы отправили неверные POST-данные');
    }
}

echo $page->getPage($result['message']);
