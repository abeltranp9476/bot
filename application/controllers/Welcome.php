<?php
defined('BASEPATH') or exit('No direct script access allowed');

use Telegram\Bot\Api;

class Welcome extends CI_Controller
{
	public $token = '705632855:AAGOUkE4ChdBepPAaZj9C-afmOsDRkmFKOM';

	public $listsExclusion = [
		['username' => 'DantesV3', 'group' => 'comprayventadecasas'],
		['username' => 'GroupAnonymousBot', 'group' => 'comprayventadecasas']
	];

	public $userCounter = 0;


	public function index()
	{
		echo "Bot Server";
	}


	public function recive()
	{
		$this->load->model('newmembers_model', 'newmembers');

		$telegram = new Api($this->token);
		$json = file_get_contents("php://input");
		$request = json_decode($json, $assoc = false);
		$group = $request->message->chat->username;
		$chatId = $request->message->chat->id;
		$fromUser = $request->message->from->username;
		$fromId = $request->message->from->id;
		$type = $request->message->chat->type;
		$text = $request->message->text;
		$textId = $request->message->message_id;
		$newparticipant = $request->message->new_chat_participant->id;
		$isBot = $request->message->new_chat_participant->is_bot;
		$leftparticipant = $request->message->left_chat_member->id;

		//Obteniendo configuracion y la hacemos global
		$config = $this->newmembers->getConfig($group);

		if ($config->active && !$this->isExclusion($fromUser, $group) && $this->CheckType($type)) {
			if (!$this->isRecommendedAll($group, $fromId)) {
				if ($config->is_users_add && !$text == '') {
					$this->delete($textId, $chatId);
					$reply_markup = $telegram->replyKeyboardHide();
					$total = $config->users_add - $this->userCounter;
					$telegram->sendMessage([
						'chat_id' => $chatId,
						'text' => "Hola @$fromUser , no puedes escribir en este grupo hasta que no agregues $total de tus contactos.",
						'reply_markup' => $reply_markup
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
					'text' => "Hola @$fromUser , no están permitido enlaces ni menciones en este grupo.",
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

			if (substr($text, 0, 9) == '/register') {
				$groupIn =  trim(preg_replace('/\/register/', '', $text));

				$keyboard = [' Registrar ' => '/register'];

				$reply_markup = $telegram->replyKeyboardMarkup([
					'keyboard' => $keyboard,
					'resize_keyboard' => true,
					'one_time_keyboard' => true
				]);

				$telegram->sendMessage([
					'chat_id' => $chatId,
					'text' => "Hola @$fromUser , se ha registrado su grupo: $groupIn. Por favor, contacte a la administración para proceder a activar su cuenta.",
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
		if ($this->userCounter >= $this->usersAdd) {
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
}
