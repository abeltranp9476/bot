<?php
defined('BASEPATH') or exit('No direct script access allowed');

use Telegram\Bot\Api;

class Welcome extends CI_Controller
{
	public $token = '705632855:AAGOUkE4ChdBepPAaZj9C-afmOsDRkmFKOM';

	public $listsExclusion = [
		['username' => 'GroupAnonymousBot', 'group' => 'comprayventadecasas'],
		//['username' => 'GroupAnonymousBot', 'group' => 'detectongrupo'],
		['username' => 'GroupAnonymousBot', 'group' => 'revolicompraventa']
	];


	public $userCounter = 0;
	public $fromUser = '';
	public $useradd = 0;

	public function index()
	{
		echo "Bot Server";
	}


	public function recive()
	{
		$this->load->model('newmembers_model', 'newmembers');
		$this->load->model('telegramsession_model', 'tSession');
		$this->load->model('groups_model', 'groups');

		$telegram = new Api($this->token);
		$json = file_get_contents("php://input");
		$request = json_decode($json, $assoc = false);
		$group = $request->message->chat->username;
		$chatId = $request->message->chat->id;
		$fromUser = $request->message->from->username;
		$fromName = $request->message->from->firstname;
		$fromId = $request->message->from->id;
		$type = $request->message->chat->type;
		$text = $request->message->text;
		$textId = $request->message->message_id;
		$newparticipant = $request->message->new_chat_participant->id;
		$isBot = $request->message->new_chat_participant->is_bot;
		$leftparticipant = $request->message->left_chat_member->id;

		$this->fromUser = $fromUser;
		//Obteniendo configuracion y la hacemos global
		$config = $this->newmembers->getConfig($group);

		$this->useradd = $config->users_add;

		if ($config->active && !$this->isExclusion($fromUser, $group) && $this->CheckType($type)) {
			if (!$this->isRecommendedAll($group, $fromId)) {
				if ($config->is_users_add && !$text == '') {
					$this->delete($textId, $chatId);
					$reply_markup = $telegram->replyKeyboardHide();
					$total = $config->users_add - $this->userCounter;
					$personalizado = $config->message_user_add;
					if (!$personalizado == '') {
						$mensaje = $this->parseText($config->message_user_add);
					} else {
						if ($fromUser == '') {
							$nombre = "*$fromName*";
						} elseif (!$fromName == '') {
							$nombre = '*' . $fromName . '* (@' . $fromUser . ')';
						} else {
							$nombre = "@$fromUser";
						}
						$mensaje = "Hola $nombre, no puedes escribir en este grupo hasta que no agregues contactos. Faltan *$total*.";
					}
					$telegram->sendMessage([
						'chat_id' => $chatId,
						'text' => "$mensaje",
						'reply_markup' => $reply_markup,
						'parse_mode' => 'markdown'
					]);
					exit;
				}
			}

			//Filtro los mensajes
			if ($config->is_disable_Spamm && $this->filter($text)) {
				$this->delete($textId, $chatId);
				$reply_markup = $telegram->replyKeyboardHide();
				$telegram->sendMessage([
					'chat_id' => $chatId,
					'text' => "Hola @$fromUser, no están permitido enlaces ni menciones en este grupo.",
					'reply_markup' => $reply_markup
				]);
				exit;
			}

			//Cuando alguien agrega un nuevo participante
			if (!empty($newparticipant)) {
				//Chequeo que si está activado no agregar bots y si no es un bot procede.
				if ($config->is_disable_Add_Bots && $isBot) {
					$this->banMember($newparticipant, $chatId);
					$reply_markup = $telegram->replyKeyboardHide();
					$telegram->sendMessage([
						'chat_id' => $chatId,
						'text' => "Hola @$fromUser , no puedes agregar bots al grupo.",
						'reply_markup' => $reply_markup
					]);
					exit;
				}

				$data = [
					'from_id' => $fromId,
					'group_name' => $group,
					'id_participant_added' => $newparticipant
				];
				$this->newmembers->create($data);
				if ($config->is_delete_User_Add_Message) {
					$this->delete($textId, $chatId);
				}
				exit;
			}

			//Cuando un participante con usuarios añadidos abandona el grupo
			if (!empty($leftparticipant)) {
				$this->newmembers->delete($leftparticipant, $group);
				if ($config->is_delete_User_Add_Message) {
					$this->delete($textId, $chatId);
				}
				exit;
			}
		} elseif ($type == 'private') {
			//Aquí van los comandos del bot

			/* Comando: /start */
			if (substr($text, 0, 6) == '/start') {
				if ($this->tSession->checkUser($fromId) == 0) {
					$data = [
						'user_id' => $fromId,
						'command' => ''
					];
					$this->tSession->create($data);
				}


				$reply_markup = $telegram->replyKeyboardHide();

				$telegram->sendMessage([
					'chat_id' => $chatId,
					'text' => "Por favor, use /register para añadir su grupo a nuestro sistema.",
					'reply_markup' => $reply_markup
				]);
			}

			/* Comando: /register */
			if (substr($text, 0, 9) == '/register') {

				$data = [
					'command' => '/register'
				];
				$this->tSession->update($fromId, $data);

				$reply_markup = $telegram->replyKeyboardHide();

				$telegram->sendMessage([
					'chat_id' => $chatId,
					'text' => "Introduzca el nombre de su grupo (Ej: @group_name)...",
					'reply_markup' => $reply_markup
				]);

				exit;
			}

			if ($this->tSession->getCommand($fromId) == '/register') {
				if (!$this->groups->isExist($this->cleanGroupName($text))) {
					$data = [
						'group_name' => $this->cleanGroupName($text),
						'user' => $fromId,
						'active' => 0,
						'is_users_add' => 1,
						'users_add' => 20,
						'is_delete_User_Add_Message' => 1,
						'is_disable_Add_Bots' => 1,
						'is_disable_Spamm' => 1,
						'message_user_add' => ''
					];
					$this->groups->create($data);

					$reply_markup = $telegram->replyKeyboardHide();

					$telegram->sendMessage([
						'chat_id' => $chatId,
						'text' => "¡Grupo $text registrado correctamente! Agrege nuestro Bot a su grupo y hágalo Administrador. Luego póngase en contacto con nosotros para activarle el servicio.",
						'reply_markup' => $reply_markup
					]);
				} else {
					$reply_markup = $telegram->replyKeyboardHide();

					$telegram->sendMessage([
						'chat_id' => $chatId,
						'text' => "El grupo $text ya fue registrado por otro cliente. Por favor, ejecute nuevamente el comando: /register e intente con otro grupo.",
						'reply_markup' => $reply_markup
					]);
				}

				$data1 = [
					'command' => '',
					'group_name' => $this->cleanGroupName($text)
				];

				$this->tSession->update($fromId, $data1);

				exit;
			}

			/* Comando: /setSpam */
			if (substr($text, 0, 8) == '/setspam') {

				$data = [
					'command' => '/setspam'
				];
				$this->tSession->update($fromId, $data);
				$keyboard = [
					['Activar', 'Desactivar']
				];

				$reply_markup = $telegram->replyKeyboardMarkup([
					'keyboard' => $keyboard,
					'resize_keyboard' => true,
					'one_time_keyboard' => true
				]);

				$telegram->sendMessage([
					'chat_id' => $chatId,
					'text' => "Seleccione la configuración para esta opción:",
					'reply_markup' => $reply_markup
				]);

				exit;
			}

			if ($this->tSession->getCommand($fromId) == '/setspam') {
				$this->isValid($text, $chatId);
				if ($this->groups->getUserId($this->tSession->getGroup($fromId)) == $fromId) {
					$data = [
						'is_disable_Spamm' => $this->changeValue($text)
					];
					$this->groups->update($this->tSession->getGroup($fromId), $data);
				}

				$data1 = [
					'command' => ''
				];

				$this->tSession->update($fromId, $data1);

				$reply_markup = $telegram->replyKeyboardHide();
				if ($text == 'Desactivar') {
					$mensaje = 'desactivado';
				}

				if ($text == 'Activar') {
					$mensaje = 'activado';
				}

				$telegram->sendMessage([
					'chat_id' => $chatId,
					'text' => "Se ha $mensaje la opción de protección AntiSpam.",
					'reply_markup' => $reply_markup
				]);

				exit;
			}

			/* Comando: /setBots */
			if (substr($text, 0, 8) == '/setbots') {

				$data = [
					'command' => '/setbots'
				];
				$this->tSession->update($fromId, $data);

				$keyboard = [
					['Activar', 'Desactivar']
				];

				$reply_markup = $telegram->replyKeyboardMarkup([
					'keyboard' => $keyboard,
					'resize_keyboard' => true,
					'one_time_keyboard' => true
				]);

				$telegram->sendMessage([
					'chat_id' => $chatId,
					'text' => "Seleccione la configuración para esta opción:",
					'reply_markup' => $reply_markup
				]);

				exit;
			}

			if ($this->tSession->getCommand($fromId) == '/setbots') {
				$this->isValid($text, $chatId);
				if ($this->groups->getUserId($this->tSession->getGroup($fromId)) == $fromId) {
					$data = [
						'is_disable_Add_Bots' => $this->changeValue($text)
					];
					$this->groups->update($this->tSession->getGroup($fromId), $data);
				}

				$data1 = [
					'command' => ''
				];

				$this->tSession->update($fromId, $data1);

				if ($text == 'Desactivar') {
					$mensaje = 'desactivado';
				}

				if ($text == 'Activar') {
					$mensaje = 'activado';
				}

				$reply_markup = $telegram->replyKeyboardHide();
				$telegram->sendMessage([
					'chat_id' => $chatId,
					'text' => "Se ha $mensaje rechazar bots añadidos por los usuarios.",
					'reply_markup' => $reply_markup
				]);

				exit;
			}


			/* Comando: /setUserAddMessage */
			if (substr($text, 0, 19) == '/setusersaddmessage') {

				$data = [
					'command' => '/setusersaddmessage'
				];
				$this->tSession->update($fromId, $data);

				$keyboard = [
					['Activar', 'Desactivar']
				];

				$reply_markup = $telegram->replyKeyboardMarkup([
					'keyboard' => $keyboard,
					'resize_keyboard' => true,
					'one_time_keyboard' => true
				]);

				$telegram->sendMessage([
					'chat_id' => $chatId,
					'text' => "Seleccione la configuración para esta opción:",
					'reply_markup' => $reply_markup
				]);

				exit;
			}

			if ($this->tSession->getCommand($fromId) == '/setusersaddmessage') {
				$this->isValid($text, $chatId);
				if ($this->groups->getUserId($this->tSession->getGroup($fromId)) == $fromId) {
					$data = [
						'is_delete_User_Add_Message' => $this->changeValue($text)
					];
					$this->groups->update($this->tSession->getGroup($fromId), $data);
				}

				$data1 = [
					'command' => ''
				];

				$this->tSession->update($fromId, $data1);

				$reply_markup = $telegram->replyKeyboardHide();
				if ($text == 'Desactivar') {
					$mensaje = 'desactivado';
				}

				if ($text == 'Activar') {
					$mensaje = 'activado';
				}

				$telegram->sendMessage([
					'chat_id' => $chatId,
					'text' => "Se ha $mensaje eliminar mensajes de usuarios agregados y eliminados.",
					'reply_markup' => $reply_markup
				]);

				exit;
			}



			/* Comando: /setUserAdd */
			if (substr($text, 0, 11) == '/setusersdd') {

				$data = [
					'command' => '/setusersdd'
				];
				$this->tSession->update($fromId, $data);

				$reply_markup = $telegram->replyKeyboardHide();

				$telegram->sendMessage([
					'chat_id' => $chatId,
					'text' => "Escriba un número entre 1 y 100:",
					'reply_markup' => $reply_markup
				]);

				exit;
			}

			if ($this->tSession->getCommand($fromId) == '/setusersdd') {
				$this->isRange($text, 1, 100, $chatId);
				if ($this->groups->getUserId($this->tSession->getGroup($fromId)) == $fromId) {
					$data = [
						'users_add' => $text
					];
					$this->groups->update($this->tSession->getGroup($fromId), $data);
				}

				$data1 = [
					'command' => ''
				];

				$this->tSession->update($fromId, $data1);

				$reply_markup = $telegram->replyKeyboardHide();

				$telegram->sendMessage([
					'chat_id' => $chatId,
					'text' => "Se ha configurado la cantidad de usuarios a añadir en: $text",
					'reply_markup' => $reply_markup
				]);

				exit;
			}

			/* Comando: /setIsUserAdd */
			if (substr($text, 0, 13) == '/setisuseradd') {

				$data = [
					'command' => '/setisuseradd'
				];
				$this->tSession->update($fromId, $data);

				$keyboard = [
					['Activar', 'Desactivar']
				];

				$reply_markup = $telegram->replyKeyboardMarkup([
					'keyboard' => $keyboard,
					'resize_keyboard' => true,
					'one_time_keyboard' => true
				]);

				$telegram->sendMessage([
					'chat_id' => $chatId,
					'text' => "Seleccione la configuración para esta opción:",
					'reply_markup' => $reply_markup
				]);

				exit;
			}

			if ($this->tSession->getCommand($fromId) == '/setisuseradd') {
				$this->isValid($text, $chatId);
				if ($this->groups->getUserId($this->tSession->getGroup($fromId)) == $fromId) {
					$data = [
						'is_users_add' => $this->changeValue($text)
					];
					$this->groups->update($this->tSession->getGroup($fromId), $data);
				}

				$data1 = [
					'command' => ''
				];

				$this->tSession->update($fromId, $data1);

				$reply_markup = $telegram->replyKeyboardHide();
				if ($text == 'Desactivar') {
					$mensaje = 'desactivado';
				}

				if ($text == 'Activar') {
					$mensaje = 'activado';
				}

				$telegram->sendMessage([
					'chat_id' => $chatId,
					'text' => "Se ha $mensaje añadir miembros al grupo para poder escribir.",
					'reply_markup' => $reply_markup
				]);

				exit;
			}

			/* Comando: /setMessageUserAdd */
			if (substr($text, 0, 18) == '/setmessageuseradd') {

				$data = [
					'command' => '/setmessageuseradd'
				];
				$this->tSession->update($fromId, $data);

				$keyboard = [
					['Vaciar', 'Ignorar']
				];

				$reply_markup = $telegram->replyKeyboardMarkup([
					'keyboard' => $keyboard,
					'resize_keyboard' => true,
					'one_time_keyboard' => true
				]);

				$message = "*Escriba su mensaje personalizado*
Utilice los comodines:
__%user%__ - nombre de usuario
__%total%__ - Valor configurado
__%counter%__ - Contador de usuarios
__%reminder%__ - Cuántos faltan";

				$telegram->sendMessage([
					'chat_id' => $chatId,
					'text' => $message,
					'reply_markup' => $reply_markup,
					'parse_mode' => 'markdown'
				]);
				exit;
			}

			if ($this->tSession->getCommand($fromId) == '/setmessageuseradd') {
				if ($this->groups->getUserId($this->tSession->getGroup($fromId)) == $fromId) {


					if ($text === 'Ignorar') {
						$reply_markup = $telegram->replyKeyboardHide();
						$telegram->sendMessage([
							'chat_id' => $chatId,
							'text' => "Se ha cancelado el comando.",
							'reply_markup' => $reply_markup
						]);

						$data1 = [
							'command' => ''
						];

						$this->tSession->update($fromId, $data1);
						exit;
					}

					if ($text === 'Vaciar') {
						$reply_markup = $telegram->replyKeyboardHide();

						$telegram->sendMessage([
							'chat_id' => $chatId,
							'text' => "Se ha eliminado su mensaje personalizado.",
							'reply_markup' => $reply_markup
						]);

						$data = [
							'message_user_add' => ''
						];
						$this->groups->update($this->tSession->getGroup($fromId), $data);

						$data1 = [
							'command' => ''
						];

						$this->tSession->update($fromId, $data1);

						exit;
					}

					$data = [
						'message_user_add' => $text
					];
					$this->groups->update($this->tSession->getGroup($fromId), $data);
				}

				$data1 = [
					'command' => ''
				];

				$this->tSession->update($fromId, $data1);

				$reply_markup = $telegram->replyKeyboardHide();

				$telegram->sendMessage([
					'chat_id' => $chatId,
					'text' => "Se ha establecido su mensaje personalizado.",
					'reply_markup' => $reply_markup
				]);

				exit;
			}
		}
	}

	private function isRecommendedAll($group, $userId)
	{
		$this->load->model('newmembers_model', 'newmembers');
		$this->userCounter = $this->newmembers->countAll($group, $userId);
		$config = $this->newmembers->getConfig($group);
		if ($this->userCounter >= $config->users_add) {
			return True;
		} else {
			return False;
		}
	}

	private function CheckType($type_check)
	{
		$types = ['supergroup'];
		foreach ($types as $type) {
			if ($type_check === $type) {
				return True;
			}
		}
		return False;
	}

	private function isExclusion($user, $group)
	{
		foreach ($this->listsExclusion as $list) {
			if ($user === $list['username'] && $group === $list['group']) {
				return True;
			}
		}
		return False;
	}

	private function filter($text)
	{
		if (preg_match("/((http|https|ftp|www)[^\s]+)/", $text)) {
			return True;
		}

		if (preg_match("/(@[^\s]+)/", $text)) {
			return True;
		}

		if (preg_match("/([a-z:0-9])([.][^\s]+)([a-z])/", $text)) {
			return True;
		}

		return False;
	}

	private function delete($messageId, $chatId)
	{
		$data = [
			'message_id' => $messageId,
			'chat_id' => $chatId
		];

		$resultado = file_get_contents("https://api.telegram.org/bot$this->token/deleteMessage?" . http_build_query($data));
		return $resultado;
	}

	private function banMember($userId, $chatId)
	{
		$data = [
			'user_id' => $userId,
			'chat_id' => $chatId
		];

		$resultado = file_get_contents("https://api.telegram.org/bot$this->token/banChatMember?" . http_build_query($data));
		return $resultado;
	}

	private function isRange($number, $min, $max, $chatId)
	{
		if ($number >= $min and $number <= $max) {
			return true;
		}
		$telegram = new Api($this->token);
		$reply_markup = $telegram->replyKeyboardHide();
		$telegram->sendMessage([
			'chat_id' => $chatId,
			'text' => "¡Por favor, escriba un número entre $min y $max! Intente nuevamente...",
			'reply_markup' => $reply_markup
		]);
		exit;
	}

	private function isValid($option, $chatId)
	{
		$optionList = ['Activar', 'Desactivar'];

		foreach ($optionList as $options) {
			if ($option === $options) {
				return true;
			}
		}
		$telegram = new Api($this->token);
		$keyboard = [
			['Activar', 'Desactivar']
		];

		$reply_markup = $telegram->replyKeyboardMarkup([
			'keyboard' => $keyboard,
			'resize_keyboard' => true,
			'one_time_keyboard' => true
		]);
		$telegram->sendMessage([
			'chat_id' => $chatId,
			'text' => "¡No es válido el valor que desea aplicar! Intente nuevamente...",
			'reply_markup' => $reply_markup
		]);
		exit;
	}

	private function changeValue($option)
	{
		if ($option === 'Activar') {
			return 1;
		}

		if ($option === 'Desactivar') {
			return 0;
		}
	}

	private function parseText($text)
	{
		$total = $this->useradd  - $this->userCounter;
		$text = preg_replace("/%user%/", $this->fromUser, $text);
		$text = preg_replace("/%counter%/", $this->userCounter, $text);
		$text = preg_replace("/%remaning%/", $total, $text);
		$text = preg_replace("/%total%/", $this->useradd, $text);
		return $text;
	}

	private function cleanGroupName($group)
	{
		$group = preg_replace("/@/", '', $group);
		return trim($group);
	}
}
