<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Visit_model extends CI_Model
{
    private function branchIds($userId)
    {
        $this->load->model('User_model');
        return $this->User_model->branchIds($userId);
    }

    private function applyBranchScope($ids, $alias = 'v', $column = 'branch_id')
    {
        if (is_array($ids)) {
            if (!$ids) $this->db->where('1 = 0', NULL, FALSE);
            else $this->db->where_in($alias.'.'.$column, $ids);
        }
    }

    public function summary($userId, $from, $to)
    {
        // Resolve access before composing the analytics query. CodeIgniter 3
        // shares one Query Builder state per connection; a nested RBAC query
        // would otherwise reset the outer FROM clause.
        $ids = $this->branchIds($userId);
        $this->db->select('COALESCE(SUM(v.total_visits),0) total_visits, COUNT(DISTINCT v.branch_id) reporting_branches', FALSE)
            ->from('visit_daily v')->where('v.visit_date >=', $from)->where('v.visit_date <=', $to);
        $this->applyBranchScope($ids);
        return $this->db->get()->row();
    }

    public function daily($userId, $from, $to)
    {
        $ids = $this->branchIds($userId);
        $this->db->select('v.visit_date, SUM(v.total_visits) total_visits')->from('visit_daily v')
            ->where('v.visit_date >=', $from)->where('v.visit_date <=', $to)->group_by('v.visit_date')->order_by('v.visit_date');
        $this->applyBranchScope($ids);
        return $this->db->get()->result();
    }

    public function byBranch($userId, $from, $to)
    {
        $ids = $this->branchIds($userId);
        $this->db->select('b.id, b.code, b.name, b.status, b.last_sync_at, COALESCE(SUM(v.total_visits),0) total_visits', FALSE)
            ->from('branches b')->join('visit_daily v', 'v.branch_id=b.id AND v.visit_date >= '.$this->db->escape($from).' AND v.visit_date <= '.$this->db->escape($to), 'left')
            ->group_by('b.id')->order_by('total_visits', 'desc');
        $this->applyBranchScope($ids, 'b', 'id');
        return $this->db->get()->result();
    }

    public function upsert($branchId, $date, $total, $generatedAt = NULL)
    {
        $sql = 'INSERT INTO visit_daily (branch_id, visit_date, total_visits, source_generated_at, synced_at) VALUES (?, ?, ?, ?, ?) '
             . 'ON DUPLICATE KEY UPDATE total_visits=VALUES(total_visits), source_generated_at=VALUES(source_generated_at), synced_at=VALUES(synced_at)';
        return $this->db->query($sql, array($branchId, $date, (int)$total, $generatedAt, date('Y-m-d H:i:s')));
    }
}
