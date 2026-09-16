<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Visit_catalog_metrics extends CI_Migration
{
    public function up()
    {
        $columns = array(
            'total_referred_patients' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_visits',
            'new_patients' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_referred_patients',
            'returning_patients' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER new_patients',
            'total_outpatient_visits' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER returning_patients',
            'total_inpatient_visits' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_outpatient_visits',
            'total_emergency_patients' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_inpatient_visits',
            'payment_methods_json' => 'LONGTEXT NULL AFTER total_emergency_patients',
            'diagnoses_json' => 'LONGTEXT NULL AFTER payment_methods_json',
            'medicines_json' => 'LONGTEXT NULL AFTER diagnoses_json',
        );
        foreach ($columns as $name => $definition) {
            if (!$this->db->field_exists($name, 'visit_daily')) {
                $this->db->query('ALTER TABLE visit_daily ADD `'.$name.'` '.$definition);
            }
        }
    }

    public function down()
    {
        foreach (array('medicines_json','diagnoses_json','payment_methods_json','total_emergency_patients','total_inpatient_visits','total_outpatient_visits','returning_patients','new_patients','total_referred_patients') as $name) {
            if ($this->db->field_exists($name, 'visit_daily')) $this->db->query('ALTER TABLE visit_daily DROP COLUMN `'.$name.'`');
        }
    }
}
