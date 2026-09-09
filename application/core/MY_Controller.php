<?php defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller
{
    protected $currentUser;
    protected $currentPermissions = array();

    public function __construct()
    {
        parent::__construct();
        $this->config->load('satukes');
        date_default_timezone_set($this->config->item('satukes_timezone'));
        $this->load->model('User_model');
        $this->currentUser = $this->User_model->current();
        if ($this->currentUser) $this->currentPermissions = $this->User_model->permissions($this->currentUser->id);
    }

    protected function requireLogin()
    {
        if (!$this->currentUser) {
            $this->session->set_userdata('redirect_after_login', current_url());
            redirect('login');
        }
    }

    protected function requirePermission($permission)
    {
        $this->requireLogin();
        if (!in_array($permission, $this->currentPermissions, TRUE)) {
            show_error('Anda tidak memiliki hak akses untuk halaman ini.', 403, 'Akses ditolak');
        }
    }

    protected function render($view, array $data = array())
    {
        $data['currentUser'] = $this->currentUser;
        $permissions = $this->currentPermissions;
        $data['can'] = function ($permission) use ($permissions) {
            return in_array($permission, $permissions, TRUE);
        };
        $data['contentView'] = $view;
        $this->load->view('layouts/app', $data);
    }
}
