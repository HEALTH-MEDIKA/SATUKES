<?php defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model
{
    public function authenticate($email, $password)
    {
        $user = $this->db->where('email', strtolower(trim($email)))->where('is_active', 1)->get('users')->row();
        if (!$user || !password_verify($password, $user->password_hash)) return NULL;
        $this->db->where('id', $user->id)->update('users', array('last_login_at' => date('Y-m-d H:i:s')));
        return $user;
    }

    public function current()
    {
        $id = (int) $this->session->userdata('user_id');
        return $id ? $this->db->where('id', $id)->where('is_active', 1)->get('users')->row() : NULL;
    }

    public function can($userId, $permission)
    {
        $query = $this->db->select('p.id')->from('permissions p')
            ->join('role_permissions rp', 'rp.permission_id = p.id')
            ->join('user_roles ur', 'ur.role_id = rp.role_id')
            ->where('ur.user_id', $userId)->where('p.name', $permission)->limit(1)->get();
        return $query->num_rows() > 0;
    }

    public function permissions($userId)
    {
        return array_column($this->db->distinct()->select('p.name')->from('permissions p')
            ->join('role_permissions rp','rp.permission_id=p.id')->join('user_roles ur','ur.role_id=rp.role_id')
            ->where('ur.user_id',$userId)->get()->result_array(),'name');
    }

    public function branchIds($userId)
    {
        if ($this->can($userId, 'branches.view_all')) return NULL;
        return array_map('intval', array_column($this->db->select('branch_id')->where('user_id', $userId)->get('user_branches')->result_array(), 'branch_id'));
    }

    public function all()
    {
        return $this->db->select('u.*, GROUP_CONCAT(DISTINCT r.label ORDER BY r.label SEPARATOR ", ") roles', FALSE)
            ->from('users u')->join('user_roles ur', 'ur.user_id=u.id', 'left')->join('roles r', 'r.id=ur.role_id', 'left')
            ->group_by('u.id')->order_by('u.name')->get()->result();
    }

    public function roles() { return $this->db->order_by('label')->get('roles')->result(); }

    public function save($id, array $data, array $roleIds, array $branchIds)
    {
        $this->db->trans_start();
        if ($id) $this->db->where('id', $id)->update('users', $data);
        else { $data['created_at'] = date('Y-m-d H:i:s'); $this->db->insert('users', $data); $id = $this->db->insert_id(); }
        $this->db->where('user_id', $id)->delete('user_roles');
        foreach ($roleIds as $roleId) $this->db->insert('user_roles', array('user_id'=>$id, 'role_id'=>(int)$roleId));
        $this->db->where('user_id', $id)->delete('user_branches');
        foreach ($branchIds as $branchId) $this->db->insert('user_branches', array('user_id'=>$id, 'branch_id'=>(int)$branchId));
        $this->db->trans_complete();
        return $id;
    }
}
