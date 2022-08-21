<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2008-2020
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	Luis Daniel Lucio Quiroz <dlucio@okay.com.mx>
*/

//includes
require_once "root.php";
require_once "resources/require.php";
require_once "resources/check_auth.php";

//check permisions
if (permission_exists('xml_cdr_view')) {
    //access granted
}
else {
    echo "access denied";
    exit;
}

//add multi-lingual support
$language = new text;
$text = $language->get();
$service = new ScheduledReportService();

try{
    $report = $service->getReport($_GET['id']);
}
catch (Exception $e){
    $_SESSION['flush_errors'] = [$text[$e->getMessage()]];
    header('Location: scheduled_reports.php');
}

if (!empty($_GET['download'])){
    try{
        $content = $service->getFileContent($_GET['download']);
        $type = "text/csv";
        if (substr($_GET['download'], 0, -3) == 'pdf'){
            $type = "application/pdf";
        }
        header("Content-type: ".$type."; charset=utf-8; name=\"".$_GET['download']."\"");
        header("Content-Disposition: attachment; filename=\"".$_GET['download']."\"");
        header('Expires: Mon, 26 Nov 1962 00:00:00 GMT');
        header("Last-Modified: ".gmdate("D,d M Y H:i:s")." GMT");
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        echo $content;
    }
    catch (Exception $e){
        $_SESSION['flush_errors'] = [$text[$e->getMessage()]];
    }
}


$logs = $service->getReportLog($_GET['id'], $report['report_timezone']);

$document['title'] = $text['scheduled-log_heading'];
require_once "resources/header.php";

if (!empty($_SESSION['flush_errors'])){
    echo "<div class='alert alert-danger'>";
    foreach ($_SESSION['flush_errors'] as $error){
        echo $error."<br>";
    }
    echo "</div>";
    unset($_SESSION['flush_errors']);
}

$timezones = $service->getTimezones();
echo "<div class='action_bar' id='action_bar'>\n";
echo "	<div class='heading'>";
echo "<b>".$text['scheduled-log_heading'].". Report: ".$report['report_name']."</b>";
echo "</div>\n";
echo "	<div class='actions'>\n";
echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'link'=>'scheduled_reports.php']);
echo "</div>\n";
echo "</div>\n";

echo "<table class='list'>\n";
echo "<tr class='list-header'>\n";
echo "<th>".$text['label-report_time']."</th>\n";
echo "<th>".$text['label-report_file']."</th>\n";
echo "<th>".$text['label-report_size']."</th>\n";
echo "</tr>";
foreach ($logs as $log){
    echo "<tr class='list-row'>\n";
    echo "<td class='middle'>".substr($log['dtime_tz'], 0, 19)."(".$report['report_timezone'].")</td>\n";
    echo "<td class='middle no-link'>".$log['filename']."&nbsp;[<a href='scheduled_report_log.php?id=".$_GET['id']."&download=".$log['filename']."'>download</a>]</td>\n";
    echo "<td class='middle'>".$service->getFileSize($log['filename'])."</td>\n";

    echo "</tr>";

}
echo "</table>";

require_once "resources/footer.php";
?>
