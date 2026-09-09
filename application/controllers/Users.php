<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Users extends MY_Controller
{
    public function __construct() { parent::__construct(); $this->load->model('Branch_model'); }
    public function index() { $this->requirePermission('users.manage'); $this->render('users/index',array('title'=>'Pengguna & Hak Akses','users'=>$this->User_model->all())); }
    public function create() { $this->form(NULL); }
    public function edit($id) { $this->form($this->db->where('id',(int)$id)->get('users')->row()); }

    private function form($user)
    {
        $this->requirePermission('users.manage'); if($user===NULL && $this->uri->segment(2)==='edit') show_404();
        $this->form_validation->set_rules('name','Nama','required|max_length[120]');
        $this->form_validation->set_rules('email','Email','required|valid_email|max_length[190]');
        if(!$user) $this->form_validation->set_rules('password','Kata sandi','required|min_length[10]');
        elseif($this->input->post('password')) $this->form_validation->set_rules('password','Kata sandi','min_length[10]');
        $this->form_validation->set_rules('roles[]','Peran','required');
        if($this->form_validation->run()) {
            $email=strtolower(trim($this->input->post('email',TRUE)));
            $duplicate=$this->db->where('email',$email); if($user) $duplicate->where('id !=',$user->id); $duplicate=$duplicate->count_all_results('users')>0;
            if($duplicate) $this->session->set_flashdata('error','Email sudah digunakan.');
            else {
                $data=array('name'=>trim($this->input->post('name',TRUE)),'email'=>$email,'is_active'=>$this->input->post('is_active')?1:0,'updated_at'=>date('Y-m-d H:i:s'));
                if($this->input->post('password')) $data['password_hash']=password_hash($this->input->post('password'),PASSWORD_DEFAULT);
                $id=$this->User_model->save($user?$user->id:NULL,$data,(array)$this->input->post('roles'),(array)$this->input->post('branches'));
                $this->db->insert('audit_logs',array('user_id'=>$this->currentUser->id,'action'=>$user?'user.update':'user.create','entity_type'=>'user','entity_id'=>$id,'ip_address'=>$this->input->ip_address(),'metadata'=>json_encode(array('email'=>$email)),'created_at'=>date('Y-m-d H:i:s')));
                $this->session->set_flashdata('success','Pengguna berhasil disimpan.'); redirect('users');
            }
        }
         $selectedRoles=$user?array_column($this->db->select('role_id')->where('user_id',$user->id)->get('user_roles')->result_array(),'role_id'):array();
        $selectedBranches=$user?array_column($this->db->select('branch_id')->where('user_id',$user->id)->get('user_branches')->result_array(),'branch_id'):array();
        $this->render('users/form',array('title'=>$user?'Ubah Pengguna':'Tambah Pengguna','user'=>$user,'roles'=>$this->User_model->roles(),'branches'=>$this->db->order_by('name')->get('branches')->result(),'selectedRoles'=>$selectedRoles,'selectedBranches'=>$selectedBranches));
    }
}
