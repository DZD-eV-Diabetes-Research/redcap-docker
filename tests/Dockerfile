FROM docker:cli AS docker-cli

FROM python:3.12-slim

ENV PYTHONUNBUFFERED=1

# docker binary is needed for 'docker exec -i' stdin piping used in DB restore
COPY --from=docker-cli /usr/local/bin/docker /usr/local/bin/docker

WORKDIR /tests

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY conftest.py test_*.py ./

ENTRYPOINT ["pytest"]
CMD [".", "--tb=short"]
