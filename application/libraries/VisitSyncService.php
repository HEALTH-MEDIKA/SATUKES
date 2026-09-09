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
                $data = $this->CI->faskesapiclient->visits($branch, $secret, $date, $date);
                if (!isset($data['metrics']['total_visits'])) throw new RuntimeException('Metrik total_visits tidak ditemukan.');
                $this->CI->Visit_model->upsert($branch->id, $date, $data['metrics']['total_visits'], isset($data['generated_at']) ? date('Y-m-d H:i:s', strtotime($data['generated_at'])) : NULL);
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
}
