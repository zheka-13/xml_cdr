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

$document['title'] = $text['scheduled-heading'];
require_once "resources/header.php";


$timezones = $service->getTimezones();
echo "<form name='frm' id='frm' action='scheduled_reports.php' method='post'>\n";
echo "<div class='action_bar' id='action_bar'>\n";
echo "	<div class='heading'>";
echo "<b>".$document['title']."</b>";
echo "</div>\n";
echo "	<div class='actions'>\n";
echo button::create(['type'=>'submit','label'=>$text['button-save'],'icon'=>$_SESSION['theme']['button_icon_save'],'id'=>'btn_save','collapse'=>'hide-xs']);
echo button::create(['type'=>'button','label'=>$text['button-back'],'icon'=>$_SESSION['theme']['button_icon_back'],'link'=>'scheduled_reports.php']);
echo "</div>\n";
echo "</div>\n";
echo "<input type='hidden' name='report_id' value='".$report['id']."'>\n";
echo "<input type='hidden' name='action' value='update'>\n";
echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";

echo "<tr>\n";
echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap>\n";
echo "    ".$text['label-report_name']."\n";
echo "</td>\n";
echo "<td width='70%' class='vtable' align='left'>";
echo "    <input class='formfld' type='text' name='report_name' style='width:300px' value='".$report['report_name']."'>";
echo "</td></tr>";

echo "<tr>\n";
echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap>\n";
echo "    ".$text['label-report_format']."\n";
echo "</td>\n";
echo "<td width='70%' class='vtable' align='left'>";
echo "<select name='report_format' class='formfld'>";
echo "<option value='csv' ".($report['report_format'] == "csv" ? "selected" : "")." >csv";
echo "<option value='pdf' ".($report['report_format'] == "pdf" ? "selected" : "")." >pdf";
echo "</select></td></tr>";

echo "<tr>\n";
echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap>\n";
echo "    ".$text['label-report_timezone']."\n";
echo "</td>\n";
echo "<td width='70%' class='vtable' align='left'>";
echo "<select name='report_timezone' class='formfld'>";
foreach ($timezones as $tz){
    echo "<option ".($report['report_timezone'] == $tz ? "selected" : "")." value='".$tz."'>".$tz;
}

echo "</select></td></tr>";

echo "<tr>\n";
echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap>\n";
echo "    ".$text['label-report_emails']."\n";
echo "</td>\n";
echo "<td width='70%' class='vtable' align='left'>";
echo "    <input class='formfld' type='text' name='report_emails' style='width:300px' value='".$report['report_emails']."'>";
echo "</td></tr>";

echo "<tr>\n";
echo "<td width='30%' class='vncellreq' valign='top' align='left' nowrap>\n";
echo "    ".$text['label-report_scheduled']."\n";
echo "</td>\n";
echo "<td width='70%' class='vtable' align='left'>";
echo "<select name='scheduled' class='formfld'>";
echo "<option value='day' ".($report['scheduled'] == "day" ? "selected" : "")." >Daily";
echo "<option value='week' ".($report['scheduled'] == "week" ? "selected" : "")." >Weekly";
echo "<option value='month' ".($report['scheduled'] == "month" ? "selected" : "")." >Monthly";
echo "</select></td></tr>";


echo "</table>";
echo "</form>";
require_once "resources/footer.php";
?>
