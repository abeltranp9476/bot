<?php
//Modelo: Newmembers

class Newmembers_model extends CI_Model
{

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
		$this->db->where('id_participant_added !=', $userId);
		$query = $this->db->get('newmembers');
		return $query->num_rows();
	}

	public function getConfig($group)
	{
		$this->db->where('group_name', $group);
		$query = $this->db->get('groups');
		return $query->row();
	}
}
