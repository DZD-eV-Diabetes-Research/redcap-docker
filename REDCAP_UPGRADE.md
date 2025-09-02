# How to update / upgrade

We will distinguish between environment and application.

`Environment` means the Operation System, php runtime and all its third party modules like image ImageMagick.  

`Application` means the RedCap php files. 

Both of these have different update/upgrade procedures.


## Environment Update

This is simple as all the magic happend at docker image building.

You just need to pull the newest docker image from docker hub. 

Outside of the terminal you can check at https://hub.docker.com/r/dzdde/redcap-docker/tags or https://github.com/DZD-eV-Diabetes-Research/redcap-docker/releases if there are new releases of the RedCap Docker image.

### docker compose

```bash
docker compose pull
```

and restart RedCAP

```bash
docker compose down && docker compose up -d
```

### docker

if you use plain docker run `docker pull dzdde/redcap-docker`. And restart your container.

## RedCap Application Update

