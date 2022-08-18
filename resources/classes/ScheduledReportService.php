<?php

class ScheduledReportService
{
    private $config;
    private $db;
    private $mailer;
    const FORMATS = ['csv', 'pdf'];
    const PERIODS = ['day', 'week', 'month'];

    public function __construct()
    {
        $this->config = include 'schedule_reports_config.php';
        $this->db = new database;
        $this->config['storage_path'] = rtrim($this->config['storage_path'], "/");
    }

    public function getReportUser()
    {
        return $this->config['report_user'];
    }

    public function getReportPermissions()
    {
        return $this->config['report_permissions'];
    }

    /**
     * @throws Exception
     */
    public function saveReport($report_id, $report_format, $content)
    {
        $file = $this->getStorage()."/".$this->getFileName($report_id, $report_format);
        file_put_contents($file, $content);
        return $file;
    }

    /**
     * @param $report
     * @param $file
     * @return void
     */
    public function sendReport($report, $file)
    {
        $subject = "Cdr report ".$report['report_name']." on host ".$this->getHostname()." ".date("d.m.Y");
        $message = "Cdr report ".$report['report_name']." on host ".$this->getHostname()." ".date("d.m.Y");
        $mail = new PHPMailer();
        if (!empty($this->config['smtp']['host'])){
            $mail->IsSMTP();
            $mail->Timeout = 10;
            $mail->SMTPSecure = $this->config['smtp']['encryption'];
            $mail->Host = $this->config['smtp']['host'];
            $mail->Port = $this->config['smtp']['port'];
            if (!empty($this->config['smtp']['username'])){
                $mail->SMTPAuth = true;
                $mail->Username = $this->config['smtp']['username'];
                $mail->Password = $this->config['smtp']['password'];
            }
            if (!empty($this->config['smtp']['encryption'])){
                $mail->SMTPOptions = [
                    "ssl" => [
                        "verify_peer" => false,
                        "verify_peer_name" => false,
                        "allow_self_signed" => true,
                    ]
                ];
            }
        }
        if (!empty($this->config['email_from'])){
            $mail->SetFrom($this->config['email_from']);
            $mail->AddReplyTo($this->config['email_from']);
        }
        $mail->Subject = $subject;
        $mail->MsgHTML($message);
        $mail->clearAllRecipients();
        $emails = explode(",", $report['report_emails']);
        foreach ($emails as $email){
            $mail->AddAddress(trim($email));
        }
        $mail->addAttachment($file, basename($file));
        if (!$mail->Send()) {
            if (isset($mail->ErrorInfo) && strlen($mail->ErrorInfo) > 0) {
                error_log($mail->ErrorInfo);
            }
        }
    }

    /**
     * @param $report
     * @param $file
     * @return void
     */
    public function logReport($report, $file){
        $query = "insert into v_scheduled_reports_logs (report_id, filename, dtime) values (:id, :filename, now()) returning id";
        $data = $this->db->execute($query, [
            "id" => $report['id'],
            "filename" => basename($file)
        ]);
        if (empty($report['last_sent'])){
            $query = "update v_scheduled_reports set last_sent = ((now()::date + '1 day'::interval + '00:00:00'::time) at time zone report_timezone)::timestamp(0), last_log_id = :log_id where id = :id";
        }
        else{
            $query = "update v_scheduled_reports set last_sent = now(), last_log_id = :log_id where id = :id";
        }
        $this->db->execute($query, [
            "id" => $report['id'],
            "log_id" => !empty($data[0]['id']) ? $data[0]['id'] : 0,
        ]);
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
     * @param $report
     * @return mixed
     */
    public function buildReportParams($report){
        parse_str($report['report_params'], $params);
        $params['answer_stamp_begin'] = "";
        $params['answer_stamp_end'] = "";
        $params['end_stamp_begin'] = "";
        $params['end_stamp_end'] = "";
        if ($report['scheduled'] == 'day'){
            $start = new DateTime('yesterday');
            $params['start_stamp_begin'] = $start->format('Y-m-d 00:00');
            $params['start_stamp_end'] = $start->format('Y-m-d 23:59');
        }
        if ($report['scheduled'] == 'week'){
            $start = new DateTime('Monday last week');
            $end = new DateTime('Sunday last week');
            $params['start_stamp_begin'] = $start->format('Y-m-d 00:00');
            $params['start_stamp_end'] = $end->format('Y-m-d 23:59');
        }
        if ($report['scheduled'] == 'month'){
            $start = new DateTime('first day of last month');
            $end = new DateTime('last day of last month');
            $params['start_stamp_begin'] = $start->format('Y-m-d 00:00');
            $params['start_stamp_end'] = $end->format('Y-m-d 23:59');
        }
        return $params;
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

    public function getPendingReports()
    {
        $where = ["'day'"];
        if (date('j') == 1){
            $where[] = "'month'";
        }
        if (date('N') == 1){
            $where[] = "'week'";
        }
        $where_string = " ((now() at time zone report_timezone)::date  > (last_sent at time zone report_timezone)::date or last_sent is null) and scheduled in (".implode(",", $where).") ";

        $query = "select * from v_scheduled_reports where ".$where_string." order by  id asc limit 5";
        return  $this->db->select($query, []);

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

    /**
     * @return mixed|string
     * @throws Exception
     */
    private function getStorage()
    {
        if (!empty($this->config['storage_path'])){
            if (is_writable($this->config['storage_path'])){
                return $this->config['storage_path'];
            }
        }
        if (is_writable("/tmp")){
            return "/tmp";
        }
        throw new Exception("No writable storage found");
    }

    private function getFileName($id, $format)
    {
        $filename = "cdr_report_".$id;
        $host = $this->getHostname();
        if (!empty($host)){
            $filename .= "_".$host;
        }
        return $filename."_".date("Y-m-d").".".$format;
    }

    private function getHostname(){
        $hostname = "";
        if (is_file("/etc/hostname") && is_readable("/etc/hostname")){
            $hostname = trim(file_get_contents("/etc/hostname"));
        }
        return $hostname;
    }


}