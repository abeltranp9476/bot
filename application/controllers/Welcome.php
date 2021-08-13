<?php
defined('BASEPATH') or exit('No direct script access allowed');

use Telegram\Bot\Api;

class Welcome extends CI_Controller
{
	public $usersAdd = 20;
	public $userCounter = 0;

	public function index()
	{
		$this->load->view('welcome_message');
	}

	public function recive()
	{
		$this->load->model('newmembers_model', 'newmembers');
		$telegram = new Api('705632855:AAGOUkE4ChdBepPAaZj9C-afmOsDRkmFKOM');
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
		$leftparticipant = $request->message->left_chat_member->id;

		if ($this->newmembers->isActiveGroup($group) && !$this->isExclusion($fromUser)) {
			if ($this->isRecommendedAll($group, $fromId) && $this->CheckType($type)) {
				//$this->newmembers->notificar('Si');
			} else {
				if (!$text == '') {
					$this->newmembers->eliminar($textId, $chatId);
					$reply_markup = $telegram->replyKeyboardHide();
					$total = $this->usersAdd - $this->userCounter;
					$telegram->sendMessage([
						'chat_id' => $chatId,
						'text' => "Hola @$fromUser , no puedes escribir en este grupo hasta que no agregues $total de tus contactos.",
						'reply_markup' => $reply_markup
					]);
				}
			}

			//Cuando alguien agrega un nuevo participante
			if (!empty($newparticipant)) {
				$data = [
					'from_id' => $fromId,
					'group_name' => $group,
					'id_participant_added' => $newparticipant
				];
				$this->newmembers->create($data);
			}

			//Cuando un participante con usuarios añadidos abandona el grupo
			if (!empty($leftparticipant)) {
				$this->newmembers->delete($leftparticipant, $group);
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

	private function isExclusion($user)
	{
		$lists = [];
		foreach ($lists as $list) {
			if ($user === $list) {
				return True;
			}
		}
		return False;
	}
}
