<?php defined('BASEPATH') OR exit('No direct script access allowed');

class VisitSyncService
{
    private $CI;
    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model(array('Branch_model','Visit_model'));
        $this->CI->load->library(array('secretvault','FaskesApiClient'));
    }

    public function sync($branch, $from, $to, $triggeredBy = NULL)
    {
        $started = microtime(TRUE); $status = 'success'; $message = NULL; $count = 0;
        try {
            $secret = $this->CI->secretvault->decrypt($branch->secret_ciphertext);
            $cursor = new DateTimeImmutable($from); $end = new DateTimeImmutable($to);
            while ($cursor <= $end) {
                $date = $cursor->format('Y-m-d');
                $catalog = $this->CI->faskesapiclient->visitCatalog($branch, $secret, $date, $date);
                $metrics = $this->normalizeCatalog($catalog);
                $generatedAt = isset($catalog['patient_status']['generated_at']) ? date('Y-m-d H:i:s', strtotime($catalog['patient_status']['generated_at'])) : NULL;
                $this->CI->Visit_model->upsertCatalog($branch->id, $date, $metrics, $generatedAt);
                $count++; $cursor = $cursor->modify('+1 day');
            }
            $this->CI->db->where('id', $branch->id)->update('branches', array('last_sync_at'=>date('Y-m-d H:i:s'), 'last_sync_status'=>'success', 'last_sync_message'=>NULL));
        } catch (Throwable $e) {
            $status = 'failed'; $message = mb_substr($e->getMessage(), 0, 500);
            $this->CI->db->where('id', $branch->id)->update('branches', array('last_sync_status'=>'failed', 'last_sync_message'=>$message));
        }
        $this->CI->db->insert('sync_logs', array('branch_id'=>$branch->id, 'date_from'=>$from, 'date_to'=>$to, 'status'=>$status, 'records_synced'=>$count, 'duration_ms'=>(int)((microtime(TRUE)-$started)*1000), 'message'=>$message, 'triggered_by'=>$triggeredBy, 'created_at'=>date('Y-m-d H:i:s')));
        return array('status'=>$status, 'message'=>$message, 'total'=>$count);
    }

    private function normalizeCatalog(array $catalog)
    {
        $required = array(
            'referred'=>'total_referred_patients', 'patient_status'=>'total_patients',
            'outpatient'=>'total_outpatient_visits', 'inpatient'=>'total_inpatient_visits',
            'emergency'=>'total_emergency_patients'
        );
        foreach ($required as $section => $field) {
            if (!isset($catalog[$section]['metrics'][$field]) || !is_numeric($catalog[$section]['metrics'][$field])) {
                throw new RuntimeException('Metrik '.$field.' tidak ditemukan atau tidak valid.');
            }
        }
        $patient = $catalog['patient_status']['metrics'];
        foreach (array('new_patients','returning_patients') as $field) {
            if (!isset($patient[$field]) || !is_numeric($patient[$field])) throw new RuntimeException('Metrik '.$field.' tidak ditemukan atau tidak valid.');
        }
        return array(
            'total_visits'=>(int)$patient['total_patients'],
            'total_referred_patients'=>(int)$catalog['referred']['metrics']['total_referred_patients'],
            'new_patients'=>(int)$patient['new_patients'], 'returning_patients'=>(int)$patient['returning_patients'],
            'total_outpatient_visits'=>(int)$catalog['outpatient']['metrics']['total_outpatient_visits'],
            'total_inpatient_visits'=>(int)$catalog['inpatient']['metrics']['total_inpatient_visits'],
            'total_emergency_patients'=>(int)$catalog['emergency']['metrics']['total_emergency_patients'],
            'payment_methods'=>$this->metricList($catalog, 'payment_methods', 'payment_methods'),
            'diagnoses'=>$this->metricList($catalog, 'diagnoses', 'diagnoses'),
            'medicines'=>$this->metricList($catalog, 'medicines', 'medicines'),
        );
    }

    private function metricList(array $catalog, $section, $field)
    {
        if (!isset($catalog[$section]['metrics'][$field]) || !is_array($catalog[$section]['metrics'][$field])) {
            throw new RuntimeException('Daftar '.$field.' tidak ditemukan atau tidak valid.');
        }
        return $catalog[$section]['metrics'][$field];
    }
}
