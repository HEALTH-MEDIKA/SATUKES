<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Visit_model extends CI_Model
{
    private function branchIds($userId)
    {
        $this->load->model('User_model');
        return $this->User_model->branchIds($userId);
    }

    private function applyBranchScope($ids, $alias = 'v', $column = 'branch_id', $branchId = NULL)
    {
        if (is_array($ids)) {
            if (!$ids) $this->db->where('1 = 0', NULL, FALSE);
            else $this->db->where_in($alias.'.'.$column, $ids);
        }
        if ($branchId !== NULL) $this->db->where($alias.'.'.$column, (int)$branchId);
    }

    public function summary($userId, $from, $to, $branchId = NULL)
    {
        // Resolve access before composing the analytics query. CodeIgniter 3
        // shares one Query Builder state per connection; a nested RBAC query
        // would otherwise reset the outer FROM clause.
        $ids = $this->branchIds($userId);
        $this->db->select('COALESCE(SUM(v.total_visits),0) total_visits, COALESCE(SUM(v.total_referred_patients),0) total_referred_patients, COALESCE(SUM(v.new_patients),0) new_patients, COALESCE(SUM(v.returning_patients),0) returning_patients, COALESCE(SUM(v.total_outpatient_visits),0) total_outpatient_visits, COALESCE(SUM(v.total_inpatient_visits),0) total_inpatient_visits, COALESCE(SUM(v.total_emergency_patients),0) total_emergency_patients, COUNT(DISTINCT v.branch_id) reporting_branches', FALSE)
            ->from('visit_daily v')->where('v.visit_date >=', $from)->where('v.visit_date <=', $to);
        $this->applyBranchScope($ids, 'v', 'branch_id', $branchId);
        return $this->db->get()->row();
    }

    public function daily($userId, $from, $to, $branchId = NULL)
    {
        $ids = $this->branchIds($userId);
        $this->db->select('v.visit_date, SUM(v.total_visits) total_visits')->from('visit_daily v')
            ->where('v.visit_date >=', $from)->where('v.visit_date <=', $to)->group_by('v.visit_date')->order_by('v.visit_date');
        $this->applyBranchScope($ids, 'v', 'branch_id', $branchId);
        return $this->db->get()->result();
    }

    public function byBranch($userId, $from, $to, $branchId = NULL)
    {
        $ids = $this->branchIds($userId);
        $this->db->select('b.id, b.code, b.name, b.status, b.last_sync_at, COALESCE(SUM(v.total_visits),0) total_visits', FALSE)
            ->from('branches b')->join('visit_daily v', 'v.branch_id=b.id AND v.visit_date >= '.$this->db->escape($from).' AND v.visit_date <= '.$this->db->escape($to), 'left')
            ->group_by('b.id')->order_by('total_visits', 'desc');
        $this->applyBranchScope($ids, 'b', 'id', $branchId);
        return $this->db->get()->result();
    }

    public function catalogLists($userId, $from, $to, $branchId = NULL)
    {
        $ids = $this->branchIds($userId);
        $this->db->select('payment_methods_json, diagnoses_json, medicines_json')->from('visit_daily v')
            ->where('v.visit_date >=', $from)->where('v.visit_date <=', $to);
        $this->applyBranchScope($ids, 'v', 'branch_id', $branchId);
        $rows = $this->db->get()->result_array();

        return array(
            'payment_methods'=>$this->aggregateList($rows, 'payment_methods_json', 'payment_code', 'payment_name', array('total_patients'), 0),
            'diagnoses'=>$this->aggregateList($rows, 'diagnoses_json', 'diagnosis_code', 'diagnosis_name', array('total_patients'), 10),
            'medicines'=>$this->aggregateList($rows, 'medicines_json', 'medicine_code', 'medicine_name', array('total_quantity','total_prescriptions'), 10),
        );
    }

    private function aggregateList(array $rows, $jsonColumn, $codeField, $nameField, array $sumFields, $limit)
    {
        $items = array();
        foreach ($rows as $row) {
            $decoded = json_decode(isset($row[$jsonColumn]) ? $row[$jsonColumn] : '', TRUE);
            if (!is_array($decoded)) continue;
            foreach ($decoded as $value) {
                if (!is_array($value) || empty($value[$codeField])) continue;
                $code = (string)$value[$codeField];
                if (!isset($items[$code])) {
                    $items[$code] = array($codeField=>$code, $nameField=>isset($value[$nameField]) ? $value[$nameField] : $code);
                    foreach ($sumFields as $field) $items[$code][$field] = 0;
                }
                foreach ($sumFields as $field) $items[$code][$field] += isset($value[$field]) ? (int)$value[$field] : 0;
            }
        }
        $sortField = $sumFields[0];
        uasort($items, function ($a, $b) use ($sortField, $codeField) {
            $byTotal = $b[$sortField] <=> $a[$sortField];
            return $byTotal ?: strcmp($a[$codeField], $b[$codeField]);
        });
        $items = array_values($limit ? array_slice($items, 0, $limit) : $items);
        foreach ($items as $index => &$item) $item['rank'] = $index + 1;
        unset($item);
        return $items;
    }

    public function upsert($branchId, $date, $total, $generatedAt = NULL)
    {
        $sql = 'INSERT INTO visit_daily (branch_id, visit_date, total_visits, source_generated_at, synced_at) VALUES (?, ?, ?, ?, ?) '
             . 'ON DUPLICATE KEY UPDATE total_visits=VALUES(total_visits), source_generated_at=VALUES(source_generated_at), synced_at=VALUES(synced_at)';
        return $this->db->query($sql, array($branchId, $date, (int)$total, $generatedAt, date('Y-m-d H:i:s')));
    }

    public function upsertCatalog($branchId, $date, array $metrics, $generatedAt = NULL)
    {
        $sql = 'INSERT INTO visit_daily (branch_id, visit_date, total_visits, total_referred_patients, new_patients, returning_patients, total_outpatient_visits, total_inpatient_visits, total_emergency_patients, payment_methods_json, diagnoses_json, medicines_json, source_generated_at, synced_at) '
             . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE total_visits=VALUES(total_visits), total_referred_patients=VALUES(total_referred_patients), new_patients=VALUES(new_patients), returning_patients=VALUES(returning_patients), total_outpatient_visits=VALUES(total_outpatient_visits), total_inpatient_visits=VALUES(total_inpatient_visits), total_emergency_patients=VALUES(total_emergency_patients), payment_methods_json=VALUES(payment_methods_json), diagnoses_json=VALUES(diagnoses_json), medicines_json=VALUES(medicines_json), source_generated_at=VALUES(source_generated_at), synced_at=VALUES(synced_at)';
        return $this->db->query($sql, array(
            $branchId, $date, (int)$metrics['total_visits'], (int)$metrics['total_referred_patients'],
            (int)$metrics['new_patients'], (int)$metrics['returning_patients'],
            (int)$metrics['total_outpatient_visits'], (int)$metrics['total_inpatient_visits'],
            (int)$metrics['total_emergency_patients'], json_encode($metrics['payment_methods'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($metrics['diagnoses'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($metrics['medicines'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $generatedAt, date('Y-m-d H:i:s')
        ));
    }
}
