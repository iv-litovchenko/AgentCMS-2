# TLS для prod (proxy)

Положите сюда файлы, которые ожидает Nginx в контейнере `proxy`:

- `cert.pem` — сертификат (full chain)
- `key.pem` — приватный ключ

Запуск:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

Переменные: `PUBLIC_BASE_DOMAIN`, `PUBLIC_HTTP_PORT=80`, `PUBLIC_HTTPS_PORT=443`, при необходимости `TLS_CERTS_DIR`.
