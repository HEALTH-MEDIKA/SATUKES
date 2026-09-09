<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Faskes_nomenclature extends CI_Migration
{
    private $labels = array(
        'branches.view' => array('Lihat faskes', 'Lihat cabang'),
        'branches.view_all' => array('Lihat semua faskes', 'Lihat semua cabang'),
        'branches.manage' => array('Kelola faskes', 'Kelola cabang'),
    );

    public function up()
    {
        foreach ($this->labels as $name => $labels) {
            $this->db->where('name', $name)->update('permissions', array('label' => $labels[0]));
        }
    }

    public function down()
    {
        foreach ($this->labels as $name => $labels) {
            $this->db->where('name', $name)->update('permissions', array('label' => $labels[1]));
        }
    }
}
