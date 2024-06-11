# Simple docker compose example

> 🔋🛑 Batteries not included! Due to the way how REDCap is licensed, you still need to provide the REDCap source-code/php-scripts.  
> See the `services`->`redcap`->`volumes` chapter in the `docker-compose-yaml` file.

This is the most basic docker compose to start a local instance of REDCap.
This example should run as is (if you supply a copy of REDCap). 

## How to Start

Create a local copy of the `docker-compose.yaml`-file in this directory or clone the repo with

```bash
git clone git@github.com:DZD-eV-Diabetes-Research/redcap-docker.git && \
cd redcap-docker/examples/local_instance_basic`
```

Deposit your copy of REDCap to `./data/redcap`. 
The `./data/redcap` directory should contain the `index.php`,`database.php`,... files

Update to newest image:

`docker compose pull`


Start the container:

`docker compose up -d`

Wait for a healthy state:

`docker compose ps` should mark both containers as (`healthy`)

e.g.
```bash
> docker compose ps
NAME                        IMAGE                     COMMAND                  SERVICE       CREATED         STATUS                   PORTS
dev_compose-db-1            mysql:lts                 "docker-entrypoint.s…"   db            8 minutes ago   Up 8 minutes (healthy)   0.0.0.0:3306->3306/tcp, :::3306->3306/tcp, 33060/tcp
dev_compose-redcap-1        dev_compose-redcap        "docker-php-entrypoi…"   redcap        8 minutes ago   Up 8 minutes (healthy)   0.0.0.0:80->80/tcp, :::80->80/tcp
```

Check for any error in the logs if status is `unhealthy`:

`docker compose logs`

If everything is healthy you can visit:

visit http://localhost to see the instance