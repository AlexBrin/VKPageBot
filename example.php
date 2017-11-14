<?php
/**
 * @author AlexBrin
 */

require_once 'VKPageBot.php';

class Bot extends VKPageBot {

	public static function message($event, $config) { // Конфиг не обязательно, можно не указывать
		$messageId = array_shift($event); // Получаем данные из массива события
		$flag = array_shift($event);
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

}



$bot = new Bot();
$bot->addHandler(VKPageBot::EVENT_NEW_MESSAGE, 'Bot', 'message', [
	'inputOnly' => true
]);
// $bot->addHandler(VKPageBot::EVENT_FRIEND_ONLINE, 'Bot', 'friendOnline');
// $bot->addHandler(VKPageBot::EVENT_READ_OUTPUT, 'Bot', 'readOutput');
// $bot->addHandler(VKPageBot::EVENT_USER_WRITING, 'Bot', 'userWriting');

$bot->loop();

?>