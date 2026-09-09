<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends MY_Controller
{
    public function index()
    {
        $this->requirePermission('dashboard.view'); $this->load->model(array('Visit_model','Branch_model'));
        $to = $this->input->get('date_to', TRUE) ?: date('Y-m-d');
        $from = $this->input->get('date_from', TRUE) ?: date('Y-m-d', strtotime('-6 days'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $from > $to || (strtotime($to)-strtotime($from))/86400 > 31) { $from=date('Y-m-d', strtotime('-6 days')); $to=date('Y-m-d'); }
        $branches = $this->Branch_model->visibleTo($this->currentUser->id);
        $this->render('dashboard/index', array('title'=>'Dashboard Monitoring Faskes', 'from'=>$from, 'to'=>$to, 'summary'=>$this->Visit_model->summary($this->currentUser->id,$from,$to), 'daily'=>$this->Visit_model->daily($this->currentUser->id,$from,$to), 'branchStats'=>$this->Visit_model->byBranch($this->currentUser->id,$from,$to), 'branchCount'=>count($branches)));
    }
}
