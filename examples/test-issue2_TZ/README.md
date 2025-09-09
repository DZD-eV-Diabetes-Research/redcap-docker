# Simple docker compose example with TZ set

This is just a debug example for https://github.com/DZD-eV-Diabetes-Research/redcap-docker/issues/2

With `TZ` set to `America/Matamoros` (UTC−05:00 or also called "CDT")  the log files are now TZ aware

e.g: 
```
...
redcap-1       | 127.0.0.1 - - [09/Sep/2025:09:26:58 -0500] "GET /index.php HTTP/1.1" 200 72215 "-" "curl/7.88.1"`
redcap-cron-1  | #### Cron output (09/09/2025 09:28:00 CDT):
....
```

