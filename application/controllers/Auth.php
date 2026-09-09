<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Auth extends MY_Controller
{
    public function login()
    {
        if ($this->currentUser) redirect('dashboard');
        $error = NULL;
        if ($this->input->method() === 'post') {
            $email = strtolower(trim($this->input->post('email', TRUE))); $ip = $this->input->ip_address();
            $blocked = $this->db->where('email', $email)->where('ip_address', $ip)->where('success', 0)->where('created_at >=', date('Y-m-d H:i:s', time()-900))->count_all_results('login_attempts') >= 5;
            if ($blocked) $error = 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.';
            else {
                $user = $this->User_model->authenticate($email, (string)$this->input->post('password'));
                $this->db->insert('login_attempts', array('email'=>$email, 'ip_address'=>$ip, 'success'=>$user ? 1 : 0, 'created_at'=>date('Y-m-d H:i:s')));
                if ($user) {
                    $this->session->sess_regenerate(TRUE); $this->session->set_userdata('user_id', $user->id);
                    $target = $this->session->userdata('redirect_after_login') ?: 'dashboard'; $this->session->unset_userdata('redirect_after_login'); redirect($target);
                }
                $error = 'Email atau kata sandi tidak sesuai.';
            }
        }
        $this->load->view('auth/login', array('error'=>$error));
    }

    public function logout()
    {
        if ($this->input->method() !== 'post') show_error('Method tidak diizinkan.', 405);
        $this->session->sess_destroy(); redirect('login');
    }
}
