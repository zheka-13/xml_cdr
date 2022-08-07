<?php

class ScheduledReportService
{

    const FORMATS = ['csv', 'pdf'];
    const PERIODS = ['day', 'week', 'month'];

    public function __construct()
    {
        $this->db = new database;
    }

    /**
     * @return array
     */
    public function getTimezones()
    {
        $tz = [];
        $query = "select * from pg_timezone_names where name ilike '%Australia%' and position('posix' in name) = 0";
        $data = $this->db->select($query, []);
        foreach ($data as $row){
            $tz[] = $row['name'];
        }
        return $tz;
    }

    /**
     * @param $report_name
     * @param $report_format
     * @param $report_timezone
     * @param $report_emails
     * @param $period
     * @return void
     * @throws Exception
     */
    public function addReport($report_name, $report_format, $report_timezone, $report_emails, $period)
    {
        $emails = $this->validateEmails($report_emails);
        $this->validateData($report_name, $emails, $period, $report_format);
        $params = [
            "report_name" => $report_name,
            "report_format" => $report_format,
            "report_timezone" => $report_timezone,
            "report_emails" => implode(',', $emails),
            "scheduled" => $period,
            "report_params" => $_SESSION['xml_cdr']['last_query'],
            "domain_uuid" => $_SESSION['domain_uuid']
        ];

        $query = "insert into v_scheduled_reports (report_name, report_format, report_timezone, report_emails, scheduled, report_params, domain_uuid )
        values (:report_name, :report_format, :report_timezone, :report_emails, :scheduled, :report_params, :domain_uuid)";
        $this->db->execute($query, $params);
    }

    /**
     * @param $id
     * @param $report_name
     * @param $report_format
     * @param $report_timezone
     * @param $report_emails
     * @param $period
     * @return void
     * @throws Exception
     */
    public function updateReport($id, $report_name, $report_format, $report_timezone, $report_emails, $period)
    {
        if (empty($id)){
            throw new Exception('error-report_not_found');
        }
        $emails = $this->validateEmails($report_emails);
        $this->validateData($report_name, $emails, $period, $report_format);
        $params = [
            "report_name" => $report_name,
            "report_format" => $report_format,
            "report_timezone" => $report_timezone,
            "report_emails" => implode(',', $emails),
            "scheduled" => $period,
            "domain_uuid" => $_SESSION['domain_uuid'],
            "id" => $id
        ];

        $query = "update v_scheduled_reports set 
                               report_name = :report_name, 
                               report_format = :report_format, 
                               report_timezone = :report_timezone, 
                               report_emails = :report_emails, 
                               scheduled = :scheduled
                               where domain_uuid = :domain_uuid and id = :id";
        $this->db->execute($query, $params);
    }

    /**
     * @return mixed
     */
    public function getReports()
    {
        $query = "select * from v_scheduled_reports where domain_uuid = :domain_uuid order by id asc";
        return $this->db->select($query, [
            "domain_uuid" => $_SESSION['domain_uuid']
        ]);
    }

    /**
     * @param $id
     * @return void
     */
    public function delReport($id)
    {
        $query = "delete from v_scheduled_reports where domain_uuid = :domain_uuid and id = :id";
        $this->db->execute($query, [
            "domain_uuid" => $_SESSION['domain_uuid'],
            "id" => $id
        ]);
    }

    /**
     * @param $id
     * @return mixed
     * @throws Exception
     */
    public function getReport($id)
    {
        $query = "select * from v_scheduled_reports where domain_uuid = :domain_uuid and id = :id order by id asc";
        $data =  $this->db->select($query, [
            "domain_uuid" => $_SESSION['domain_uuid'],
            "id" => $id
        ]);
        if (empty($data)) {
            throw new Exception('error-report_not_found');
        }
        return $data[0];
    }

    /**
     * @param $emails_string
     * @return array
     */
    private function validateEmails($emails_string){
        $emails = [];
        $tmp = explode(',', $emails_string);
        foreach ($tmp as $email){
            $email = trim($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)){
                $emails[] = $email;
            }
        }
        return $emails;
    }

    /**
     * @param $report_name
     * @param $emails
     * @param $period
     * @param $report_format
     * @return void
     * @throws Exception
     */
    private function validateData($report_name, $emails, $period, $report_format)
    {
        if (empty($report_name)){
            throw new Exception('error-report_name');
        }

        if (empty($emails)){
            throw new Exception('error-report_emails');
        }
        if (!in_array($period, self::PERIODS)){
            throw new Exception('error-report_period');
        }
        if (!in_array($report_format, self::FORMATS)){
            throw new Exception('error-report_format');
        }
    }


}