<?php
//Modelo: Groups

class Groups_model extends CI_Model
{
	public function create($data)
	{
		$this->db->insert('groups', $data);
	}


	public function update($group, $data)
	{
		$this->db->where('group_name', $group);
		$query = $this->db->update('groups', $data);
	}

	public function isExist($group)
	{
		$this->db->select('id');
		$this->db->where('group_name', $group);
		$query = $this->db->get('groups');
		if ($query->num_rows() > 0) {
			return true;
		}
		return false;
	}

	public function getUserId($group)
	{
		$this->db->select('user');
		$this->db->where('group_name', $group);
		$query = $this->db->get('groups');
		$row = $query->row();
		return $row->user;
	}
}
