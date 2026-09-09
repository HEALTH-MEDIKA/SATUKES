<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Migrate extends CI_Controller
{
    public function index()
    {
        if (!is_cli()) show_404();

        $this->load->library('migration');
        if (!$this->migration->latest()) {
            echo $this->migration->error_string().PHP_EOL;
            exit(1);
        }

        echo 'Migrasi database selesai pada versi '.$this->config->item('migration_version').'.'.PHP_EOL;
    }
}
