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

        $params = $service->buildReportParams($report);

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
            $file = $service->saveReport($report, $res);
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