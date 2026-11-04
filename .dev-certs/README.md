# Dev TLS (mkcert)

Сгенерируйте сертификаты:

```bash
./scripts/tls-dev-setup.sh
```

Затем подключите override:

```bash
docker compose -f docker-compose.yml -f docker-compose.tls-dev.yml up -d proxy
```

Файлы `cert.pem`, `key.pem`, `meta.yaml` создаются скриптом и не коммитятся.
