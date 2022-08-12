# xml_cdr
Fusionpbx Call Detail Records app with scheduled reports

# App configuration

* See file **schedule_reports_config.php** for configuration options


# Setting up cron

* add line to global crontab with valid path to app cron script

`*/5     *       *       *       *       root  php  /path/to/file/schedule_reports_cron.php`
