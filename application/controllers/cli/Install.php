<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Install extends CI_Controller
{
    public function index()
    {
        if(!is_cli()) show_404();
        $email=getenv('INSTALL_ADMIN_EMAIL'); $password=getenv('INSTALL_ADMIN_PASSWORD');
        if(ENVIRONMENT !== 'production') {
            $email=$email ?: 'admin@example.id';
            $password=$password ?: 'admin123345';
        }
        if(!$email || !$password || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<10) { echo "Set INSTALL_ADMIN_EMAIL dan INSTALL_ADMIN_PASSWORD (minimal 10 karakter), lalu jalankan: php index.php cli/install".PHP_EOL; exit(1); }
        $this->load->library('migration'); if(!$this->migration->latest()) { echo $this->migration->error_string().PHP_EOL; exit(1); }
        if($this->db->count_all('permissions')===0) $this->seedAccess();
        $existing=$this->db->where('email',strtolower($email))->get('users')->row();
        if(!$existing) { $this->db->insert('users',array('name'=>'Super Administrator','email'=>strtolower($email),'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'is_active'=>1,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s'))); $userId=$this->db->insert_id(); }
        else { $userId=$existing->id; $this->db->where('id',$userId)->update('users',array('password_hash'=>password_hash($password,PASSWORD_DEFAULT),'is_active'=>1,'updated_at'=>date('Y-m-d H:i:s'))); }
        $role=$this->db->where('name','super_admin')->get('roles')->row(); if(!$this->db->where(array('user_id'=>$userId,'role_id'=>$role->id))->count_all_results('user_roles')) $this->db->insert('user_roles',array('user_id'=>$userId,'role_id'=>$role->id));
        echo 'Instalasi selesai. Login sebagai '.$email.PHP_EOL;
        if(ENVIRONMENT !== 'production' && $email === 'admin@example.id' && $password === 'admin123345') echo 'Password development default: admin123345. Segera ganti setelah login.'.PHP_EOL;
    }

    private function seedAccess()
    {
        $permissions=array('dashboard.view'=>'Lihat dashboard','branches.view'=>'Lihat faskes','branches.view_all'=>'Lihat semua faskes','branches.manage'=>'Kelola faskes','visits.sync'=>'Sinkronisasi kunjungan','users.manage'=>'Kelola pengguna');
        foreach($permissions as $name=>$label) $this->db->insert('permissions',array('name'=>$name,'label'=>$label));
        $roles=array('super_admin'=>'Super Administrator','admin'=>'Administrator','analyst'=>'Analis','viewer'=>'Viewer');
        foreach($roles as $name=>$label) $this->db->insert('roles',array('name'=>$name,'label'=>$label));
        $maps=array('super_admin'=>array_keys($permissions),'admin'=>array('dashboard.view','branches.view','branches.view_all','branches.manage','visits.sync'),'analyst'=>array('dashboard.view','branches.view','visits.sync'),'viewer'=>array('dashboard.view','branches.view'));
        foreach($maps as $roleName=>$perms) { $role=$this->db->where('name',$roleName)->get('roles')->row(); foreach($perms as $permName) { $perm=$this->db->where('name',$permName)->get('permissions')->row(); $this->db->insert('role_permissions',array('role_id'=>$role->id,'permission_id'=>$perm->id)); } }
    }
}
