[Получить токен](https://oauth.vk.com/authorize?client_id=3116505&scope=friends,photos,audio,video,docs,notes,pages,status,offers,questions,wall,groups,messages,notifications,stats,ads,market,offline&redirect_uri=https://api.vk.com/blank.html&display=page&response_type=token)

[Пример создания чат-бота](/example.php)


Пример
======
Подробнее о событиях можно почитать [тут]([https://vk.com/dev/using_longpoll?f=3.%20%D0%A1%D1%82%D1%80%D1%83%D0%BA%D1%82%D1%83%D1%80%D0%B0%20%D1%81%D0%BE%D0%B1%D1%8B%D1%82%D0%B8%D0%B9)
```php
<?php
/**
 * @author AlexBrin
 */

// Пример создания простого чат-бота

require_once 'VKPageBot.php'; // Подключаем

class Bot extends VKPageBot { // Создаем наследника основной части бота

    // Создаем обработчик события
	public static function message($event, $config) { // Конфиг не обязательно, можно не указывать
		$messageId = array_shift($event); // Получаем данные из массива события
		$flag = array_shift($event); // Получаем данные из массива события
		$userId = array_shift($event);
		$timestamp = array_shift($event);
		$text = array_shift($event);
		$attachments = array_shift($event);

		$triggerMessage = null;
		foreach($config['triggers'] as $trigger => $response) { // Ищем совпадения из конфига
			$text = mb_strtolower($text);
			
			if(isset($response['users']) && is_array($response['users']) 
				&& count($response['users']) > 0 && !in_array($userId, $response['users']))
				continue;

			if(isset($response['strict']) && $response['strict']) {
				if($text != $trigger)
					continue;
			}
			elseif(strripos($text, trim($trigger)) === false) 
				continue;

			if(is_array($response['text'])) // Если вариантов несколько, то берем случайный
				$triggerMessage = $response['text'][array_rand($response['text'])];
			else
				$triggerMessage = $response['text'];
		}

		if(!$triggerMessage)
			return;

		Bot::getInstance()->sendMessage($triggerMessage, $userId, $messageId); // Отправляем сообщение
	}


	public static function friendOnline($event, $config) {
		$userId = abs(array_shift($event)); // Приходит -$userId
		$extra = array_shift($event); // тут мы можем узнать некоторые подробности

		print_r("Друг " . self::getInstance()->getUsername($userId) . " онлайн\n");
		print_r("Платформа: " . self::getInstance()->getPlatform($extra) . "\n");
	} 

	public static function readOutput($event, $config) {
		$peerId = array_shift($event);

		if($peerId > 0 && $peerId < 2000000000) {
			print_r(self::getInstance()->getUsername($peerId) . " прочитал сообщения\n");
		}
	}

	public static function userWriting($event) {
		$userId = array_shift($event);
		print_r(self::getInstance()->getUsername($userId) . " пишет сообщение\n");
	} 
	
	public static function userWritingTwo($event) {
	    print_r("Я второй обработчик \n");
	}

}



$bot = new Bot(); // Создаем экземпляр класса
// Регистрируем обработчики событий
// Первый - обработка сообщений
// Первый аргумент - ID события (подробнее в VKPageBot.php)
// Второй аргумент - название класса
// Третий аргумент -- название функции. Функция должна быть статична
$bot->addHandler(VKPageBot::EVENT_NEW_MESSAGE, 'Bot', 'message', [
	'inputOnly' => true // Указываем, что хотим читать только входящие сообщения
]);
// Регистрируем обработчик "друг онлайн"
$bot->addHandler(VKPageBot::EVENT_FRIEND_ONLINE, 'Bot', 'friendOnline');
// Регистрируем обработчич "Исходящее сообщение прочитали"
$bot->addHandler(VKPageBot::EVENT_READ_OUTPUT, 'Bot', 'readOutput');

// Регистрируем два обработчика "пользователь пишет в ЛС"
$bot->addHandler(VKPageBot::EVENT_USER_WRITING, 'Bot', 'userWriting');
$bot->addHandler(VKPageBot::EVENT_USER_WRITING, 'Bot', 'userWritingTwo');

// Запускаем бота
$bot->loop();

?>
```


Конфиг
======
 **token** - токен ВК
 **forwardMessage** - пересылать ли сообщение, на которое идет ответ

**triggers** - Это настройки для расширения 
```javascript
"что ищем": {
    "text": "Что ответим"
},
"привет": {
    "text": ["вариант 1", "вариант 2", "выбирается случайно"]
},
"гыг": {
    "strict": true, // ответ будет только если в сообщение ничего больше не будет
    "text": "ыгы"
},
"я админ": {
    "users": [340494379], // ответ получит только пользователи с указанными ID
    "text": "да, ты одмэн"
}
```