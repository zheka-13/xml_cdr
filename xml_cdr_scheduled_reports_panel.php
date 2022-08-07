<?php

$service = new ScheduledReportService();
$timezones = $service->getTimezones();
echo "<div calss='action_bar' id='scheduled_reports_panel' style='display:none'>";
echo "<div class='heading'><b>".$text['scheduled-heading']."</b></div>";
echo "<div class='actions' >";
echo "<form id='scheduled_add_form' action='scheduled_reports.php'  method='post'>\n";
echo "<input type='hidden' name='action' value='add_report'>";
echo "<table><tr>";
echo "<td><input type='text' class='formfld' style='width: 300px' name='report_name' placeholder='".$text['scheduled-report_name']."' value=''></td>";
echo "<td><select name='report_format' class='formfld'>";
echo "<option value='csv'>csv";
echo "<option value='pdf'>pdf";
echo "</select></td>";
echo "<td><select name='report_timezone' class='formfld'>";
foreach ($timezones as $tz){
    echo "<option value='".$tz."'>".$tz;
}
echo "</select></td>";
echo "<td><input type='text' style='width: 300px' class='formfld' name='report_emails' placeholder='".$text['scheduled-report_emails_placeholder']."' value=''></td>";
echo "<td><select name='scheduled' class='formfld'>";
echo "<option value='day'>daily";
echo "<option value='week'>weekly";
echo "<option value='month'>monthly";
echo "</select></td>";
echo "<td>";
echo button::create(['type'=>'submit','label'=>$text['button-add_scheduled_report'],
    'icon'=>$_SESSION['theme']['button_icon_add'],
    'title' => $text['button-add_scheduled_title'],
    'link'=>"scheduled_reports.php"]);
echo "</td>";
echo "<td>";
echo button::create(['type'=>'button','label'=>$text['button-show_scheduled_reports'],
    'icon'=>$_SESSION['theme']['button_icon_email'],
    'link'=>"scheduled_reports.php"]);
echo "</td>";
echo "</tr></table>";
echo "</form>";
echo "</div>";
echo "</div>";
?>

<script>
    function toggle_reports_panel()
    {
        if ($("#scheduled_reports_panel").is(":visible")){
            $("#scheduled_reports_panel").hide();
            return;
        }
        $("#scheduled_reports_panel").show();
    }

</script>
