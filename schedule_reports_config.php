<?php

return [
    "storage_path" => "/opt/test", //must be writable by the www-data user
    "keep_reports_period" => 90, // in days . 0 - forever, -1 - do not keep
    "report_user" => "admin",
    "use_smtp" => false,
    "smtp" => [
        "host" => "",
        "port" => 25,
        "username" => "",
        "password" => "",
        "encryption" => "",
    ],
    "report_permissions" => [
        "xml_cdr_export_csv" => true,
        "xml_cdr_export" => true,
        "xml_cdr_export_pdf" =>true,
        "xml_cdr_view" => true,
        "xml_cdr_pdd" => true,
        "xml_cdr_mos" => true,
        "xml_cdr_domain" => true
    ]
];

?>
