<?php
/**
 * @author AlexBrin
 */

class VKPageBot {
	const BASE = 'https://api.vk.com/method/';

	const EVENT_UPDATE_FLAG_MESSAGE = 1;
	const EVENT_SET_FLAG_MESSAGE = 2;
	const EVENT_REPLACE_FLAG_MESSAGE = 3;
	const EVENT_NEW_MESSAGE = 4;
	const EVENT_READ_INPUT = 6;
	const EVENT_READ_OUTPUT = 7;
	const EVENT_FRIEND_ONLINE = 8;
	const EVENT_FRIEND_OFFLINE = 9;
	const EVENT_USER_WRITING = 61;
	const EVENT_USER_CHAT_WRITING = 62;

	protected static $flags = [65536, 512, 256, 128, 64, 32, 16, 8, 4, /*2,*/ 1];

	protected static $platforms = [
		1 => 'Мобильная версия сайта или неопознанное приложение',
		2 => 'iPhone',
		3 => 'iPad',
		4 => 'Android',
		5 => 'Windows Phone',
		6 => 'Windows 8/10',
		7 => 'Полная версия сайта или неопознанное приложение'
	];

	/**
	 * @var string
	 */
	protected $token;

	/**
	 * @var array
	 */
	protected $config;

	/**
	 * @var array
	 */
	protected $longpoll = [];

	/**
	 * @var array
	 */
	protected $functions = [];

	/**
	 * @var array
	 */
	protected $data = [];

	protected static $instance;

	public function __construct($loop = false) {
		$this->config = json_decode(file_get_contents('config.json'), true);
		$this->token = $this->config['token'];
		unset($this->config['token']);
		
		$this->longpoll = $this->request('messages.getLongPollServer');

		print_r("====================\n");
		print_r("   ГОТОВ К РАБОТЕ   \n");
		print_r("====================\n");

		if($loop)
			$this->loop();

		self::$instance = &$this;
	}

	public function __destruct() {
		if($this->com)
			fclose($this->com);
	}

	public function __get($key) {
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}

	public function __set($key, $value) {
		$this->data[$key] = $value;
	}

	public function getUsername($userId) {
		$user = $this->request('users.get', [
			'user_ids' => $userId
		])[0];

		return $user['first_name'] . ' ' . $user['last_name'];
	}

	public function getPlatform($extra) {
		return self::$platforms[$extra & 0xFF];
	}

	protected function sendMessage($message, $targetId, $repliedId = null) {
		foreach($this->getConfig()['forwardFilter'] as $match)
			$message = preg_replace($match, $this->getConfig()['forwardReplace'], $message);

		$params = [
			'peer_id' => $targetId,
			'message' => $message,
			'v' => '5.38',
		];

		if($repliedId && $this->getConfig()['forwardMessage'])
			$params['forward_messages'] = $repliedId;

		$this->request('messages.send', $params);
	}

	public function loop() {
		print_r("Работаю...\n");

		$ts = $this->longpoll['ts'];
		while(true) {
			$events = file_get_contents("https://" . $this->longpoll['server'] . 
				"?act=a_check&mode=2&version=2&key=" . $this->longpoll['key'] . "&ts=" . $ts);
			$events = json_decode($events, true);
			$ts = $events["ts"];

			foreach($events["updates"] as $event) {
				switch(array_shift($event)) {

					case self::EVENT_UPDATE_FLAG_MESSAGE:
							if(!isset($this->functions[self::EVENT_UPDATE_FLAG_MESSAGE]))
								continue;

							for($i = 0; $i < count($this->functions[self::EVENT_UPDATE_FLAG_MESSAGE]); $i++) {
								$callable  = $this->functions[self::EVENT_UPDATE_FLAG_MESSAGE][$i]['class'];
								$func = $this->functions[self::EVENT_UPDATE_FLAG_MESSAGE][$i]['func'];

								$callable::$func($event, $this->getConfig());
							}
						break;

					case self::EVENT_SET_FLAG_MESSAGE:
							if(!isset($this->functions[self::EVENT_SET_FLAG_MESSAGE]))
								continue;

							for($i = 0; $i < count($this->functions[self::EVENT_SET_FLAG_MESSAGE]); $i++) {
								$callable  = $this->functions[self::EVENT_SET_FLAG_MESSAGE][$i]['class'];
								$func = $this->functions[self::EVENT_SET_FLAG_MESSAGE][$i]['func'];

								$callable::$func($event, $this->getConfig());
							}
						break;

					case self::EVENT_REPLACE_FLAG_MESSAGE:
							if(!isset($this->functions[self::EVENT_REPLACE_FLAG_MESSAGE]))
								continue;

							for($i = 0; $i < count($this->functions[self::EVENT_REPLACE_FLAG_MESSAGE]); $i++) {
								$callable  = $this->functions[self::EVENT_REPLACE_FLAG_MESSAGE][$i]['class'];
								$func = $this->functions[self::EVENT_REPLACE_FLAG_MESSAGE][$i]['func'];

								$callable::$func($event, $this->getConfig());
							}
						break;

					case self::EVENT_NEW_MESSAGE:
							if(!isset($this->functions[self::EVENT_NEW_MESSAGE]))
								continue;

							foreach(self::$flags as $_flag)
								if($_flag <= $event[1])
									$event[1] -= $_flag;

							for($i = 0; $i < count($this->functions[self::EVENT_NEW_MESSAGE]); $i++) {
								$callable  = $this->functions[self::EVENT_NEW_MESSAGE][$i]['class'];
								$func = $this->functions[self::EVENT_NEW_MESSAGE][$i]['func'];


								$params = $this->functions[self::EVENT_NEW_MESSAGE][$i]['params'];
								if(isset($params['inputOnly']) && $params['inputOnly'])
									if($event[1] == 2)
										continue;

								$callable::$func($event, $this->getConfig());
							}
						break;

					case self::EVENT_READ_INPUT:
							if(!isset($this->functions[self::EVENT_READ_INPUT]))
								continue;

							for($i = 0; $i < count($this->functions[self::EVENT_READ_INPUT]); $i++) {
								$callable  = $this->functions[self::EVENT_READ_INPUT][$i]['class'];
								$func = $this->functions[self::EVENT_READ_INPUT][$i]['func'];

								$callable::$func($event, $this->getConfig());
							}
						break;

					case self::EVENT_READ_OUTPUT:
							if(!isset($this->functions[self::EVENT_READ_OUTPUT]))
								continue;


							for($i = 0; $i < count($this->functions[self::EVENT_READ_OUTPUT]); $i++) {
								$callable  = $this->functions[self::EVENT_READ_OUTPUT][$i]['class'];
								$func = $this->functions[self::EVENT_READ_OUTPUT][$i]['func'];

								$callable::$func($event, $this->getConfig());
							}
						break;

					case self::EVENT_FRIEND_ONLINE:
							if(!isset($this->functions[self::EVENT_FRIEND_ONLINE]))
								continue;


							for($i = 0; $i < count($this->functions[self::EVENT_FRIEND_ONLINE]); $i++) {
								$callable  = $this->functions[self::EVENT_FRIEND_ONLINE][$i]['class'];
								$func = $this->functions[self::EVENT_FRIEND_ONLINE][$i]['func'];

								$callable::$func($event, $this->getConfig());
							}
						break;

					case self::EVENT_FRIEND_OFFLINE:
							if(!isset($this->functions[self::EVENT_FRIEND_OFFLINE]))
								continue;

							for($i = 0; $i < count($this->functions[self::EVENT_FRIEND_OFFLINE]); $i++) {
								$callable  = $this->functions[self::EVENT_FRIEND_OFFLINE][$i]['class'];
								$func = $this->functions[self::EVENT_FRIEND_OFFLINE][$i]['func'];

								$callable::$func($event, $this->getConfig());
							}
						break;

					case self::EVENT_USER_WRITING:
							if(!isset($this->functions[self::EVENT_USER_WRITING]))
								continue;

							for($i = 0; $i < count($this->functions[self::EVENT_USER_WRITING]); $i++) {
								$callable  = $this->functions[self::EVENT_USER_WRITING][$i]['class'];
								$func = $this->functions[self::EVENT_USER_WRITING][$i]['func'];

								$callable::$func($event, $this->getConfig());
							}
						break;

					case self::EVENT_USER_CHAT_WRITING:	
							if(!isset($this->functions[self::EVENT_USER_CHAT_WRITING]))
								continue;

							for($i = 0; $i < count($this->functions[self::EVENT_USER_CHAT_WRITING]); $i++) {
								$callable  = $this->functions[self::EVENT_USER_CHAT_WRITING][$i]['class'];
								$func = $this->functions[self::EVENT_USER_CHAT_WRITING][$i]['func'];

								$callable::$func($event, $this->getConfig());
							}
						break;

				}

			}
		}
	}

	public function addHandler($eventType, $className, $functionName, $params = []) {
		if(!isset($this->functions[$eventType]))
			$this->functions[$eventType] = [];

		$this->functions[$eventType][] = [
			'class' => $className,
			'func' => $functionName,
			'params' => $params,
		];
	}

	public function request($method, $params = []) {
		$params['access_token'] = $this->token;
		$params = http_build_query($params);

		$response = file_get_contents(self::BASE . $method . '?' . $params);
		$response = json_decode($response, true);

		if(isset($response['error']))
			throw new Exception($response['error']['error_msg'], $response['error']['error_code']);

		return $response['response'];
	}

	public function getToken() {
		return $this->token;
	}

	public function getConfig() {
		return $this->config;
	}

	public static function getInstance() {
		return self::$instance;
	}

}

?>