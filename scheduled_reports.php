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
if (permission_exists('scheduled_reports')) {
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

if (isset($_POST['action']) && $_POST['action'] == 'add_report'){
    try{
        $service->addReport($_POST['report_name'], $_POST['report_format'], $_POST['report_timezone'], $_POST['report_emails'], $_POST['scheduled']);
        header('Location: scheduled_reports.php');
    }
    catch (Exception $e){
        $_SESSION['flush_errors'] = [$text[$e->getMessage()]];
        header('Location: xml_cdr.php?'.(!empty($_SESSION['xml_cdr']['last_query']) ? $_SESSION['xml_cdr']['last_query'] : ""));
        exit();
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'update'){
    try{
        $service->updateReport($_POST['report_id'], $_POST['report_name'], $_POST['report_format'], $_POST['report_timezone'], $_POST['report_emails'], $_POST['scheduled']);
        header('Location: scheduled_reports.php');
    }
    catch (Exception $e){
        $_SESSION['flush_errors'] = [$text[$e->getMessage()]];
        header('Location: scheduled_reports.php');
        exit();
    }
}
if (isset($_POST['action']) && $_POST['action'] == 'del_report'){
    $service->delReport($_POST['report_id']);
    header('Location: scheduled_reports.php');
}

$document['title'] = $text['scheduled-heading'];
require_once "resources/header.php";

if (!empty($_SESSION['flush_errors'])){
    echo "<div class='alert alert-danger'>";
    foreach ($_SESSION['flush_errors'] as $error){
        echo $error."<br>";
    }
    echo "</div>";
    unset($_SESSION['flush_errors']);
}



echo "<div class='action_bar' id='action_bar'>\n";
echo "	<div class='heading'>";
echo "<b>".$document['title']."</b>";
echo "</div>\n";
echo "	<div class='actions'>\n";
echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'link'=>'xml_cdr.php']);
echo "</div>\n";
echo "	<div style='clear: both;'></div>\n";
echo "</div>\n";

echo "<table class='list'>\n";
echo "<tr class='list-header'>\n";
echo "<th>".$text['label-report_name']."</th>\n";
echo "<th>".$text['label-report_format']."</th>\n";
echo "<th>".$text['label-report_timezone']."</th>\n";
echo "<th>".$text['label-report_emails']."</th>\n";
echo "<th>".$text['label-report_scheduled']."</th>\n";
echo "<th>".$text['label-report_filter']."</th>\n";
echo "<th>".$text['label-report_info']."</th>\n";
echo "<th>".$text['label-report_manage']."</th>\n";
echo "</tr>";
$reports = $service->getReports();

foreach ($reports as $report){
    echo "<tr class='list-row'>\n";
    echo "<td class='middle'>".$report['report_name']."</td>\n";
    echo "<td class='middle'>".$report['report_format']."</td>\n";
    echo "<td class='middle'>".$report['report_timezone']."</td>\n";
    echo "<td class='middle'>".str_replace(",", "<br>", $report['report_emails'])."</td>\n";
    echo "<td class='middle'>".$text['period-'.$report['scheduled']]."</td>\n";
    echo "<td>";
    $filter = $service->getSearchParams($report['report_params'], $text);
    foreach ($filter as $key => $val){
        echo "<strong>".$key."</strong> = ".$val."<br>";
    }
    echo "</td>\n";
    echo "<td>";
    if (!empty($report['last_sent_tz'])) {
        echo $text['scheduled-last_sent'] . ": " . substr($report['last_sent_tz'], 0, 19) . "(" . $report['report_timezone'] . ")<br>";
    }
    echo "<a href='xml_cdr.php?".$service->makeSampleQuery($report['report_params'])."' 
        title='".$text['scheduled-sample_link_title']."'>".$text['scheduled-sample_link']."</a>";
    echo "</td>";
    echo "<td>";
    echo "<form action='scheduled_reports.php'  method='post'>\n";
    echo "<input type='hidden' name='action' value='del_report'>";
    echo "<input type='hidden' name='report_id' value='".$report['id']."'>";
    echo button::create(['type'=>'submit','label'=>$text['button-delete'],'icon'=>$_SESSION['theme']['button_icon_delete'],'link'=>'',
        "style" => "margin-bottom:5px;margin-top:6px"]);
    echo button::create(['type'=>'button','label'=>$text['button-edit'],'icon'=>$_SESSION['theme']['button_icon_edit'],'link'=>'scheduled_report_edit.php?id='.$report['id'],
        "style" => "margin-bottom:5px"]);
    echo button::create(['type'=>'button','label'=>$text['button-log'],'icon'=>$_SESSION['theme']['button_icon_export'],'link'=>'scheduled_report_log.php?id='.$report['id'],
        "style" => "margin-bottom:5px"]);
    echo "</form>";

    echo "</td>\n";
    echo "</tr>";

}
echo "</table>";
require_once "resources/footer.php";
?>
