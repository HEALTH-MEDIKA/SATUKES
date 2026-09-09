<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Branches extends MY_Controller
{
    public function __construct() { parent::__construct(); $this->load->model('Branch_model'); }

    public function index()
    {
        $this->requirePermission('branches.view');
        $this->render('branches/index', array('title'=>'Fasilitas Kesehatan', 'branches'=>$this->Branch_model->visibleTo($this->currentUser->id)));
    }

    public function create() { $this->form(NULL); }
    public function edit($id) { $this->form($this->Branch_model->findVisible($id,$this->currentUser ? $this->currentUser->id : 0)); }

    private function form($branch)
    {
        $this->requirePermission('branches.manage');
        if ($branch === NULL && $this->uri->segment(2) === 'edit') show_404();
        $this->form_validation->set_rules('code','Kode','required|alpha_dash|max_length[30]');
        $this->form_validation->set_rules('name','Nama','required|max_length[150]');
        $this->form_validation->set_rules('base_url','Base URL','required|valid_url|max_length[255]');
        $this->form_validation->set_rules('cons_id','Cons ID','required|max_length[100]');
        if (!$branch) $this->form_validation->set_rules('secret_key','Secret Key','required|min_length[32]');
        elseif ($this->input->post('secret_key')) $this->form_validation->set_rules('secret_key','Secret Key','min_length[32]');
        if ($this->form_validation->run()) {
            $url = rtrim(trim($this->input->post('base_url', TRUE)), '/'); $parts = parse_url($url);
            if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), array('http','https'), TRUE) || isset($parts['user']) || (ENVIRONMENT==='production' && strtolower($parts['scheme'])!=='https')) {
                $this->session->set_flashdata('error','Base URL harus HTTP/HTTPS dan tidak boleh berisi kredensial.');
            } else {
                $data = array('code'=>strtoupper(trim($this->input->post('code',TRUE))), 'name'=>trim($this->input->post('name',TRUE)), 'base_url'=>$url, 'cons_id'=>trim($this->input->post('cons_id',TRUE)), 'timezone'=>$this->input->post('timezone',TRUE) ?: 'Asia/Jakarta', 'status'=>$this->input->post('status') === 'inactive' ? 'inactive' : 'active');
                $secret = trim((string)$this->input->post('secret_key'));
                if ($secret !== '') { $this->load->library('secretvault'); $data['secret_ciphertext']=$this->secretvault->encrypt($secret); }
                $duplicate=$this->db->where('code',$data['code']); if($branch) $duplicate->where('id !=',$branch->id); $duplicate=$duplicate->count_all_results('branches')>0;
                if($duplicate) { $this->session->set_flashdata('error','Kode faskes sudah digunakan.'); redirect(current_url()); }
                $id = $this->Branch_model->save($branch ? $branch->id : NULL, $data);
                $this->db->insert('audit_logs', array('user_id'=>$this->currentUser->id,'action'=>$branch?'branch.update':'branch.create','entity_type'=>'branch','entity_id'=>$id,'ip_address'=>$this->input->ip_address(),'metadata'=>json_encode(array('code'=>$data['code'])),'created_at'=>date('Y-m-d H:i:s')));
                $this->session->set_flashdata('success','Faskes berhasil disimpan.'); redirect('faskes');
            }
        }
        $this->render('branches/form', array('title'=>$branch?'Ubah Faskes':'Tambah Faskes', 'branch'=>$branch));
    }

    public function test($id)
    {
        $this->requirePermission('branches.manage'); $branch=$this->Branch_model->findVisible($id,$this->currentUser->id); if(!$branch) show_404();
        try { $this->load->library('FaskesApiClient'); $this->faskesapiclient->health($branch); $this->session->set_flashdata('success','API '.$branch->name.' dapat diakses dan sehat.'); }
        catch(Throwable $e) { $this->session->set_flashdata('error','Uji koneksi gagal: '.$e->getMessage()); }
        redirect('faskes');
    }

    public function sync($id)
    {
        $this->requirePermission('visits.sync'); $branch=$this->Branch_model->findVisible($id,$this->currentUser->id); if(!$branch) show_404();
        if($branch->status!=='active') { $this->session->set_flashdata('error','Faskes nonaktif tidak dapat disinkronkan.'); redirect('faskes'); }
        $this->load->library('visitsyncservice'); $result=$this->visitsyncservice->sync($branch,date('Y-m-d',strtotime('-6 days')),date('Y-m-d'),$this->currentUser->id);
        $this->session->set_flashdata($result['status']==='success'?'success':'error',$result['status']==='success'?'Sinkronisasi selesai.':'Sinkronisasi gagal: '.$result['message']); redirect('faskes');
    }
}
