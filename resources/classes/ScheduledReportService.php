<?php

class ScheduledReportService
{
    private $config;
    private $db;
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
    public function saveReport($report, $content)
    {
        $file = $this->getStorage()."/".$this->getFileName($report['id'], $report['report_format'], $report['domain_uuid']);
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
        $domain_name = $this->getDomainName($report['domain_uuid']);
        $subject = "Cdr report ".$report['report_name']." on host ".$domain_name." ".date("d.m.Y");
        $message = "Cdr report ".$report['report_name']." on host ".$domain_name." ".date("d.m.Y");
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
     * @throws Exception
     */
    public function logReport($report, $file){

        if (!empty($this->config['keep_reports_period']) && $this->config['keep_reports_period'] == -1){
            $query = "update v_scheduled_reports set last_sent = now(), where id = :id";
            $this->db->execute($query, [
                "id" => $report['id'],
            ]);
            if (is_file($file)){
                unlink($file);
            }
            return;
        }

        $query = "insert into v_scheduled_reports_logs (report_id, filename, dtime) values (:id, :filename, now()) returning id";
        $data = $this->db->execute($query, [
            "id" => $report['id'],
            "filename" => basename($file)
        ]);

        $query = "update v_scheduled_reports set last_sent = now(), last_log_id = :log_id where id = :id";
        $this->db->execute($query, [
            "id" => $report['id'],
            "log_id" => !empty($data[0]['id']) ? $data[0]['id'] : 0,
        ]);
        $this->deleteOldReports($report['id']);
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
        $params = $this->clearParams($params);
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
     * @param $query_string
     * @return string
     */
    public function makeSampleQuery($query_string){
        parse_str($query_string, $params);
        $params = $this->clearParams($params);
        $params['start_stamp_begin'] = date('Y-m-d 00:00');
        $params['start_stamp_end'] = date('Y-m-d 23:59');
        return http_build_query($params);
    }

    /**
     * @param $query_string
     * @param $text
     * @return array
     */
    public function getSearchParams($query_string, $text)
    {
        parse_str($query_string, $params);
        $params = $this->clearParams($params);
        $filter = [];
        foreach ($params as $param => $val){
            if (empty($val)){
                continue;
            }
            if (!empty($text['label-'.$param])){
                $filter[$text['label-'.$param]] = $val;
                continue;
            }
            if (!empty($text['table-'.$param])){
                $filter[$text['table-'.$param]] = $val;
                continue;
            }
            $filter[$param] = $val;
        }
        return $filter;
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
        $query = "select *, last_sent::timestamptz at time zone report_timezone as last_sent_tz from v_scheduled_reports where domain_uuid = :domain_uuid order by id asc";
        return $this->db->select($query, [
            "domain_uuid" => $_SESSION['domain_uuid']
        ]);
    }

    /**
     * @param $id
     * @return void
     * @throws Exception
     */
    public function delReport($id)
    {
        $query = "delete from v_scheduled_reports where domain_uuid = :domain_uuid and id = :id";
        $this->db->execute($query, [
            "domain_uuid" => $_SESSION['domain_uuid'],
            "id" => $id
        ]);
        $query = "delete from v_scheduled_reports_logs where report_id = :id";
        $this->db->execute($query, [
            "id" => $id
        ]);
        $data = glob($this->getStorage()."/".$this->getFilePatternByReport($id));
        if (!empty($data)){
            foreach ($data as $file){
                if (is_file($file)){
                    unlink($file);
                }
            }
        }
    }

    /**
     * @param $id
     * @param $timezone
     * @return mixed
     */
    public function getReportLog($id, $timezone)
    {
        $query = "select *, dtime::timestamptz at time zone '".$timezone."' as dtime_tz 
            from v_scheduled_reports_logs where  report_id = :id order by id desc";
        return  $this->db->select($query, [
            "id" => $id
        ]);

    }

    /**
     * @param $filename
     * @return false|string
     * @throws Exception
     */
    public function getFileContent($filename)
    {
        if (is_file($this->getStorage()."/".$filename)){
            return file_get_contents($this->getStorage()."/".$filename);
        }
        throw new Exception('file_not_found_error');
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
        $check_time = "((last_sent is null or ((now() at time zone report_timezone)::date > (last_sent::timestamptz at time zone report_timezone)::date)) 
        and extract(hour from now() at time zone report_timezone) = 0)";
        $where_string = "
        (scheduled = 'day'
        or 
        (scheduled = 'week' and extract(dow from now() at time zone report_timezone) = 1)
        or
        (scheduled = 'month' and extract(day from now() at time zone report_timezone) = 1))";
        $query = "select * from v_scheduled_reports where ".$check_time." and ".$where_string." order by  id asc limit 5";
        return  $this->db->select($query, []);

    }

    /**
     * @param $filename
     * @return string
     * @throws Exception
     */
    public function getFileSize($filename)
    {
        if (is_file($this->getStorage()."/".$filename)){
            return $this->humanSize(filesize($this->getStorage()."/".$filename));
        }
        return "0";
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

    private function getFileName($id, $format, $domain_uuid)
    {
        $filename = "cdr_report_".$id;
        $host = $this->getDomainName($domain_uuid);
        if (!empty($host)){
            $filename .= "_".$host;
        }
        return $filename."_".date("Y-m-d").".".$format;
    }

    /**
     * @param $id
     * @return string
     */
    private function getFilePatternByReport($id)
    {
        return  "cdr_report_".$id."_*";
    }

    /**
     * @param $uuid
     * @return mixed|string
     */
    private function getDomainName($uuid){
        $query = "select domain_name from v_domains where domain_uuid = :domain_uuid limit 1";
        $data = $this->db->select($query, [
            "domain_uuid" => $uuid
        ]);
        if (!empty($data[0]['domain_name'])){
            return $data[0]['domain_name'];
        }
        return "domain";
    }

    /**
     * @param $params
     * @return mixed
     */
    private function clearParams($params)
    {
        $params['answer_stamp_begin'] = "";
        $params['answer_stamp_end'] = "";
        $params['end_stamp_begin'] = "";
        $params['end_stamp_end'] = "";
        $params['start_stamp_begin'] = "";
        $params['start_stamp_end'] = "";
        return $params;
    }

    /**
     * @throws Exception
     */
    private function deleteOldReports($report_id)
    {
        if (!empty($this->config['keep_reports_period']) && $this->config['keep_reports_period']>0){
            $query = "select * from v_scheduled_reports_logs where report_id = :id and dtime < (now() - '".$this->config['keep_reports_period']." days'::interval) ";
            $data = $this->db->select($query, [
                "id" => $report_id
            ]);
            foreach ($data as $row){
                $query = "delete from v_scheduled_reports_logs where id = ".$row['id'];
                $this->db->execute($query, []);
                if (is_file($this->getStorage()."/".$row['filename'])){
                    unlink($this->getStorage()."/".$row['filename']);
                }
            }
        }
    }

    /**
     * @param $size
     * @param $precision
     * @return string
     */
    private function humanSize($size, $precision = 2)
    {
        $sign = 1;
        if ($size < 0) {
            $sign = -1;
            $size = abs($size);
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $power = $size > 0 ? floor(log($size, 1024)) : 0;

        return round($sign * $size / (1024 ** $power), $precision) . ' ' . $units[$power];
    }


}