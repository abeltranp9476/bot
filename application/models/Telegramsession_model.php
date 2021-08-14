<?php
//Modelo: Telegramsession

class Telegramsession_model extends CI_Model
{
	public function create($data)
	{
		$this->db->insert('telegram_session', $data);
	}


	public function update($userId, $data)
	{
		$this->db->where('user_id', $userId);
		$query = $this->db->update('telegram_session', $data);
	}

	public function checkUser($userId)
	{
		$this->db->select('user_id');
		$this->db->where('user_id', $userId);
		$query = $this->db->get('telegram_session');
		return $query->num_rows();
	}

	public function getCommand($userId)
	{
		$this->db->select('command');
		$this->db->where('user_id', $userId);
		$query = $this->db->get('telegram_session');
		$row =  $query->row();
		return $row->command;
	}
}
