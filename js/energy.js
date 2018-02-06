
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
        // даже после перевода компьютера в сон - здесь надо добавить ajax запрос на обноваление информации
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
    document.getElementById('second').innerHTML = second;
    document.getElementById('energy').innerHTML = energy;

    energy_bar = Math.round((energy/energy_max) * 100);
    document.getElementById('energy_bar_div').style.width = energy_bar + '%';

    second_bar = Math.round((second/second_max) * 100);
    document.getElementById('second_bar_div').style.width = second_bar + '%';
}

// При загрузке страницы запускаем таймер
document.addEventListener("DOMContentLoaded", timer);
