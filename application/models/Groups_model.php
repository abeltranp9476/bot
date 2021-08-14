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
}
