# Simple docker compose example with TZ set

To run it jus copy a copy of redcap to `./redcap` and do a `docker compose up -d`

This is just a debug example for https://github.com/DZD-eV-Diabetes-Research/redcap-docker/issues/2

With `TZ` set to `America/Matamoros` (UTC−05:00 or also called "CDT")  the log files are now TZ aware

* `TZ: America/Matamoros` @ https://github.com/DZD-eV-Diabetes-Research/redcap-docker/blob/c218e386009e26af4b46168ad34b8724bee07874/examples/test-issue2_TZ/docker-compose.yaml#L22
* `TZ: America/Matamoros` @ https://github.com/DZD-eV-Diabetes-Research/redcap-docker/blob/c218e386009e26af4b46168ad34b8724bee07874/examples/test-issue2_TZ/docker-compose.yaml#L61


e.g: 
```
...
redcap-1       | 127.0.0.1 - - [09/Sep/2025:09:26:58 -0500] "GET /index.php HTTP/1.1" 200 72215 "-" "curl/7.88.1"`
redcap-cron-1  | #### Cron output (09/09/2025 09:28:00 CDT):
....
```

