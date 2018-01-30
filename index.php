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
     * 1) id (int) - на всякий случай храним его и здесь, практика показывает, что это бывает полезно
     * 2) name (string) - имя
     * 3) energy (int) - текущая энергия
     * 4) energy_max (int) - максимальная энергия
     * 5) time (float) - время последнего обновления энергии
     * 6) residue (int) - остаток времени в секундах
     * 7) eweight (int) - длина полоски энергии
     * 8) time_actual (float) - текущее время
     * 9) efull (boolean) - полное ли количество энергии у пользователя
     * 10) difference (int) - разница, между временем последнего обновления энергии и текущего времени (если энергия
     *     максимальна - приравниваем данный параметр к нулю)
     * 11) addenergy - количество энергии, которую можно добавить (если энергия максимальна - приравниваем данный
     *     параметр к нулю)
     * 12) residue_new - новый остаток времени в секундах (секунд). Этот параметр нужен для более точного рассчета
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
     * Подключаемся к БД, делаем запрос на получение данных о пользователе, а также рассчитываем дополнительные параметры
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
    private function getAddUserInfo($update)
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
        } else {
            $this->user['difference'] = 0;
            $this->user['addenergy'] = 0;
            $this->user['residue_new'] = 0;
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

            // Проверяем, не стала ли энергия больше или равна максимальной, в этом случае приравниваем её к максимальной
            if ($this->user['energy'] >= $this->user['energy_max']) {
                $this->user['energy'] = $this->user['energy_max'];
            }

            // Энергия изменилась, значит нужно повторно рассчитать дополнительные параметры
            $this->getAddUserInfo(true);

            // Записываем данные в БД
            $this->db->editEnergy($this->id, $this->user['energy'], $this->user['time_actual'], $this->user['residue_new']);

            return array('sucsess' => 1, 'message' => 'Увеличение энергии успешно');

        } elseif ($value < 0) {

            // Если энергия вычитается, то первым делом нужно узнать, хватает ли у пользователя энергии
            if (abs($value) <= $this->user['energy']) {
                $this->user['energy'] = $this->user['energy'] + $value;

                // Энергия изменилась, значит нужно повторно рассчитать дополнительные параметры
                $this->getAddUserInfo(true);

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
            <style>
                * {margin: 0;padding: 0;}
                body {background: #2b2b2b;color: #f1f1f1;}
                .user {width: 250px; background: #3b3b3b; margin: 50px auto 0 auto;}
                .ava {width: 250px; height: 250px; background: url(img/ava.jpg);}
                .name {width: 100%; padding: 3px; font-size: 22px; text-align: center;}
                .energy_cont {width: 100%; height: 20px; background: #005; overflow: hidden;}
                .energy_bar {height: 20px; background: #00c; border-radius: 3px;}
                .engtext {width: 100%; height: 20px; font-size: 18px; text-align: center; margin-top: -20px;}
                button {background-color: #f44336;border: none;color: white;padding: 10px 22px;text-align: center;text-decoration: none;  display: inline-block;  font-size: 16px;  margin: 5px;}
                button:hover {background-color: #d7382c;  cursor: pointer;}
                form {float: left;}
                .formcont {width: 290px; margin: 50px auto 0 auto;}
                .userinfo {width: 350px; margin: 50px auto; border: 1px solid #444444; padding: 5px; font-family: arial, sans-serif; font-size: 14px;}
                .w600 {width: 600px;}
                .second_cont {width: 250px; height: 5px; background: #600;}
                .second_bar {width: 40px; height: 5px; background: #a00; border-radius: 2px;}
                .energy_text {width: 250px; text-align: center; font-family: arial, sans-serif; font-size: 14px;}
                .energy_text p {padding: 4px;}
                .red {font-size: 17px; text-align: center; padding: 5px; color: #f33;}
            </style>
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
                <div id="second_bar_div" class="second_bar"></div>
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
                ID: '.$this->user['id'].'<br />
                Имя: '.$this->user['name'].'<br />
                Энергии: '.$this->user['energy'].'<br />
                Максимум энергии: '.$this->user['energy_max'].'<br />
                Длина полоски энергии: '.$this->user['eweight'].'%<br />
                Время последнего. обновления: '.$this->user['time'].'<br />
                Остаток (сек.): '.$this->user['residue'].'<br /><br />
        
                Текущее время: '.$this->user['time_actual'].'<br />
                Энергия полная? '.$fullMessage.'<br /><br />';

                if (!$this->user['efull']) {
                    $content .=    'Разница времени: '.$this->user['difference'].'<br />
                                    Количество добавляемой энергии: '.$this->user['addenergy'].'<br />
                                    Новый остаток (сек.): '.$this->user['residue_new'].'<br />';
                }

        $content .= '</p>
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
            var interval = 1000; // Время одной секунды (код подразумевает, что это будут секунды, хотя это может быть и другая величина)
            var expected = Date.now() + interval;
            
            var energy = '.$this->user['energy'].'; // Текущее количество энергии
            var energy_max = '.$this->user['energy_max'].'; // Максимальное количество энергии
            var second = '.$this->user['residue_new'].'; // Текущее количество секунд
            var second_max = '.$this->cost.'; // Требуемое количество секунд для получения 1 энергии
            var energy_bar;        // Размер полоски энергии
            var second_bar;        // Размер полоски секунд
        
            // Таймер
            function timer() {
                setTimeout(step, interval);
            }
            
            // Каждую секунду увеличивает параметр секунды, энергии (если нужно)
            function step() {
                var dt = Date.now() - expected;
                if (dt > interval) {
                    // Если компьютер перевести в спящий режим, то после выхода сработает данное условие. И, разумеется, 
                    // таймер во время сна работать не будет. Если вам нужно идеальное отображение корректной информации 
                    // без обновления страницы - здесь надо добавить ajax запрос на обноваление информации
                } else {
                    if (energy < energy_max) {
                        second++;
    
                        if (second === second_max) {
                            second = 0;
                            energy++;
                        }
    
                        view();
            
                        expected += interval;
                        setTimeout(step, Math.max(0, interval - dt));
                    }
                }
            }
        
            // Обновляет значения энергии и длину полосок энергии
            function view() {
                document.getElementById(\'second\').innerHTML = second;
                document.getElementById(\'energy\').innerHTML = energy;
        
                energy_bar = Math.round((energy/energy_max) * 100);
                document.getElementById(\'energy_bar_div\').style.width = energy_bar + \'%\';
        
                second_bar = Math.round((second/second_max) * 100);
                document.getElementById(\'second_bar_div\').style.width = second_bar + \'%\';
            }
        
            // При загрузке задаем стартовые параметры и запускаем таймер
            window.onload = function(){
                document.getElementById(\'energy\').innerHTML = energy;
                document.getElementById(\'energy_max\').innerHTML = energy_max;
                document.getElementById(\'second\').innerHTML = second;
                document.getElementById(\'second_max\').innerHTML = second_max;
        
                energy_bar = Math.round((energy/energy_max) * 100);
                document.getElementById(\'energy_bar_div\').style.width = energy_bar + \'%\';
        
                second_bar = Math.round((second/second_max) * 100);
                document.getElementById(\'second_bar_div\').style.width = second_bar + \'%\';
        
                timer();
            };
        </script>
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
        $result['sucsess'] = 0;
        $result['message'] = 'Вы отправили неверные POST-данные';
    }
}

echo $page->getPage($result['message']);
