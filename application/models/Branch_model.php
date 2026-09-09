<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Branch_model extends CI_Model
{
    public function visibleTo($userId)
    {
        $this->load->model('User_model');
        $ids = $this->User_model->branchIds($userId);
        if (is_array($ids)) {
            if (!$ids) return array();
            $this->db->where_in('id', $ids);
        }
        return $this->db->order_by('name')->get('branches')->result();
    }

    public function find($id) { return $this->db->where('id', (int)$id)->get('branches')->row(); }

    public function findVisible($id, $userId)
    {
        $this->load->model('User_model'); $ids=$this->User_model->branchIds($userId);
        if(is_array($ids) && !in_array((int)$id,$ids,TRUE)) return NULL;
        return $this->find($id);
    }

    public function save($id, array $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        if ($id) { $this->db->where('id', $id)->update('branches', $data); return $id; }
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('branches', $data);
        return $this->db->insert_id();
    }
}
