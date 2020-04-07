<?php 
require_once ("class/DBController.php");
class Leads {
    private $db_handle;
    
    function __construct() {
        $this->db_handle = new DBController();
    }
    
    function getImprintList() {
        $sql = "SELECT imprint_name__c FROM imprint__c";
        $result = $this->db_handle->runBaseQuery($sql);
        return $result;
    }
    function getLeadsTodayByMicrosite($start, $end, $microsite) {
        $sql = "SELECT brand_name, COUNT(*) as Leads, '".$microsite."' AS Microsite FROM leads__c WHERE date BETWEEN '".$start."' and '".$end."' GROUP BY brand_name";
        $result = $this->db_handle->runBaseQuery($sql);
        return $result;
    }

    function getNumberofLeadsByDSO($brand,$startdate,$enddate) {
        $sql = "SELECT bname__c as Brand, lsf__c as 'Lead Source Final', fc__c as 'Final Campaign Name', paid__c as 'Paid/Non-Paid', ch__c as 'Channel', y__c as 'Ykey', m__c as 'Mkey', d__c as 'Dkey', w__c as 'WkKey', count(dso__c) AS 'Leads Actual' FROM leads__c WHERE bname__c='".$brand."' and leaddate__c BETWEEN '".$startdate."' and '".$enddate."' GROUP by dso__c";
        $result = $this->db_handle->runBaseQuery($sql);
        return $result;
    }

    function getTotalLeadsTodayByMicrosite($datetime,$microsite) {
        $sql = "SELECT count(*) as 'Total Leads', '".$microsite."' AS Microsite FROM leads__c WHERE lsf__c='".$microsite."' and leaddate__c='".$datetime."' ";
        $result = $this->db_handle->runBaseQuery($sql);
        return $result;
    }

    function getLeadsTodayForReporting($microsite,$today) {
        $sql = "SELECT bname__c, count(*) as 'leads', lsf__c as microsite FROM leads__c WHERE  leaddate__c = '".$today."' and lsf__c='".$microsite."' group by bname__c ";
        $result = $this->db_handle->runBaseQuery($sql);
        return $result;
    }

    function getLeadsTodayForReportingByDateRange($microsite,$start,$end) {
        $sql = "SELECT * FROM leadstoday WHERE (date BETWEEN '".$start."' and '".$end."') and microsite='".$microsite."' order by date ";
        $result = $this->db_handle->runBaseQuery($sql);
        return $result;
    }

    function getResults() {
        //$sql = "truncate leads_total_today__c";
        //$sql = "truncate leads__c";
        $sql = "select *from leads_total_today__c";
        $result = $this->db_handle->runBaseQuery($sql);
        return $result;
    }

    function existLead($corr_id) {
        $sql = "SELECT corr_id__c FROM leads__c WHERE corr_id__c='".$corr_id."' ";
        $result = $this->db_handle->runBaseQuery($sql);
        return $result;
    }

    function getTotalLeadsForReportingByDateRange($microsite,$start,$end) {
        $sql = "SELECT sum(total) as total FROM totalleads WHERE (date BETWEEN '".$start."' and '".$end."') and microsite='".$microsite."' ";
        $result = $this->db_handle->runBaseQuery($sql);
        return $result;
    }

    function getTotalLeadsTodayForReporting($microsite,$today) {
        $sql = "SELECT total__c, microsite__c,date__c FROM leads_total_today__c WHERE date__c = '".$today."' and microsite__c='".$microsite."' ";
        $result = $this->db_handle->runBaseQuery($sql);
        return $result;
    }

    function hasLeadReportToday($microsite,$today) {
        $sql = "SELECT * FROM leads_total_today__c WHERE microsite__c='".$microsite."' and date__c = '".$today."' ";
        $result = $this->db_handle->runBaseQuery($sql);
        return $result;
    }

    function postAllLeadReportToday($brand_name, $leads, $microsite, $today) {
        $query = "INSERT INTO leadstoday(brand_name,leads,microsite,date) VALUES (?, ?, ?, ?)";
        $paramType = "siss";
        $paramValue = array(
            $brand_name,
            $leads,
            $microsite,
            $today
        );
        
        $insertId = $this->db_handle->insert($query, $paramType, $paramValue);
        return $insertId;
    }

    function insertLead($bname,$fname,$ds,$dso,$kw,$corr_id,$lsf,$fc,$paid,$ch,$yr,$m,$d,$w,$leaddate) {
        $query = "INSERT INTO leads__c(bname__c,fname__c,ds__c,dso__c,kw__c,corr_id__c,lsf__c,fc__c,paid__c,ch__c,y__c,m__c,d__c,w__c,leaddate__c) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $paramType = "sssssssssssssss";
        $paramValue = array(
            $bname,$fname,$ds,$dso,$kw,$corr_id,$lsf,$fc,$paid,$ch,$yr,$m,$d,$w,$leaddate
        );
        
        $insertId = $this->db_handle->insert($query, $paramType, $paramValue);
        return $insertId;
    }

    function postTotalLeadsToday($total_leads, $microsite, $today) {
        $query = "INSERT INTO leads_total_today__c(total__c,microsite__c,date__c) VALUES (?, ?, ?)";
        $paramType = "iss";
        $paramValue = array(
            $total_leads,
            $microsite,
            $today
        );
        
        $insertId = $this->db_handle->insert($query, $paramType, $paramValue);
        return $insertId;
    }
    
    function getSummaryReport($microsite,$datetime,$today) {
        $lr = new Leads();
        $result = $lr->getTotalLeadsTodayByMicrosite($datetime,$microsite);
        $lr->postTotalLeadsByMicrositeToday($result,$today);
    }
    
    function postTotalLeadsByMicrositeToday($result,$today) {
        $lr = new Leads();
        $total_leads =   $result[0]['Total Leads'];
        $microsite 	 =   $result[0]['Microsite'];				
        $lr->postTotalLeadsToday($total_leads, $microsite, $today);		
    }
}
?>