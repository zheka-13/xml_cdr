<?php
if (defined('STDIN')) {
    $document_root = str_replace("\\", "/", $_SERVER["PHP_SELF"]);
    preg_match("/^(.*)\/app\/.*$/", $document_root, $matches);
    $document_root = $matches[1];
    set_include_path($document_root);
    include "root.php";
    require_once "resources/require.php";
    require_once "resources/classes/text.php";
    include_once("resources/phpmailer/class.phpmailer.php");
    include_once("resources/phpmailer/class.smtp.php");
    $_SERVER["DOCUMENT_ROOT"] = $document_root;
    $format = 'text'; //html, text

    //add multi-lingual support
    $language = new text;
    $text = $language->get();
}
else {
    die('access denied');
}

require_once "resources/classes/CronHelper.php";
require_once "resources/classes/ScheduledReportService.php";

$cronHelper = new CronHelper("scheduled_reports");

if($cronHelper->lock() !== FALSE) {
    $service = new ScheduledReportService();
    $_SESSION['username'] = $service->getReportUser();
    $_SESSION["permissions"] = $service->getReportPermissions();
    $reports = $service->getPendingReports();
    foreach ($reports as $report){
        $_REQUEST = [];
        $_REQUEST['export_format'] = $report['report_format'];
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
        foreach ($params as $key => $val){
            $_REQUEST[$key] = $val;
        }
        $domain_uuid = $report['domain_uuid'];
        $_SESSION['domain']['time_zone']['name'] = $report['report_timezone'];
        $data_body = [];
        ob_start();

        include "xml_cdr_export.php";
        $res = ob_get_contents();
        ob_end_clean();
        try {
            $file = $service->saveReport($report['id'], $report['report_format'], $res);
            if (!empty($result)) {
                $service->sendReport($report, $file);
            }
            $service->logReport($report, $file);
        }
        catch (Exception $e){
            error_log($e->getMessage());
            break;
        }
        sleep(2);
    }

    $cronHelper->unlock();
}