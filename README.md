# mirai-backend-testcase

Необходимые шаги:
1. Развернуть SQL-дамп в MySQL
2. Разместить PHP файлы в корневой каталог веб-сервера
3. Заполнить config.php
4. Доступны три действия:
- Обновление данных о смене часовых поясов http://localhost/index.php?act=update_timezones
- Получение локального времени по id города и метке UTC http://localhost/index.php?act=get_local_time&city=49286a1d-9f6c-4416-a8da-394357f55c87&utc_time=1647154799
- Получение UTC времени по id города и локальному времени http://localhost/index.php?act=get_utc_time&city=49286a1d-9f6c-4416-a8da-394357f55c87&local_time=1647140399
