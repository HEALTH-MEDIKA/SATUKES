<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Sync_visits extends CI_Controller
{
    public function index($days = 7)
    {
        if (!is_cli()) show_404(); $days=max(1,min(31,(int)$days));
        $this->load->model('Branch_model'); $this->load->library('visitsyncservice');
        $branches=$this->db->where('status','active')->get('branches')->result(); $failed=0;
        foreach($branches as $branch) { $result=$this->visitsyncservice->sync($branch,date('Y-m-d',strtotime('-'.($days-1).' days')),date('Y-m-d')); echo $branch->code.': '.$result['status'].PHP_EOL; if($result['status']!=='success') $failed++; }
        exit($failed?1:0);
    }
}
