<?php
//Modelo: Newmembers

class Newmembers_model extends CI_Model
{
	public $token = '705632855:AAGOUkE4ChdBepPAaZj9C-afmOsDRkmFKOM';

	public function notificar($mensaje, $chatId)
	{
		$data = [
			'text' => "$mensaje",
			'chat_id' => $chatId
		];

		$resultado = file_get_contents("https://api.telegram.org/bot$this->token/sendMessage?" . http_build_query($data));
		return $resultado;
	}

	public function eliminar($messageId, $chatId)
	{
		$data = [
			'message_id' => $messageId,
			'chat_id' => $chatId
		];

		$resultado = file_get_contents("https://api.telegram.org/bot$this->token/deleteMessage?" . http_build_query($data));
		return $resultado;
	}

	public function create($data)
	{
		$this->db->insert('newmembers', $data);
	}

	public function delete($userId, $group)
	{
		$this->db->where('group_name', $group);
		$this->db->where('from_id', $userId);
		$this->db->delete('newmembers');
	}

	public function cleanTable()
	{
	}

	public function countAll($group, $userId)
	{
		$this->db->select('id');
		$this->db->where('group_name', $group);
		$this->db->where('from_id', $userId);
		$query = $this->db->get('newmembers');
		return $query->num_rows();
	}

	public function isActiveGroup($group)
	{
		$this->db->select('active');
		$this->db->where('group_name', $group);
		$query = $this->db->get('groups');
		foreach ($query->result() as $row) {
			if ($row->active == 1) {
				return True;
			}
		}
		return False;
	}
}
