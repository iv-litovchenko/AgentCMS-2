# HTTPS и поддомены

## Локально (Docker)

| Что | URL |
|-----|-----|
| CMS HTTP | http://localhost:8080/ |
| CMS HTTPS | https://localhost:8443/ |
| Сервис QwenPaw | http://qwenpaw.localhost:8080/ или https://qwenpaw.localhost:8443/ |

**Сертификат:** при сборке `proxy` — self-signed OpenSSL, SAN: `localhost`, `*.localhost`, `127.0.0.1`. Браузер предупредит — для dev нормально. Удобнее: [mkcert](https://github.com/FiloSottile/mkcert) и подмена `docker/proxy/certs/` (позже).

**Поддомены:** `*.localhost` резолвится в 127.0.0.1 (Chrome/Firefox/Safari). Порт тот же, что у proxy (`8080` / `8443`).

## Продакшен (`*.sitename.ru`)

1. DNS: wildcard `*.sitename.ru` → IP сервера.
2. TLS: Let's Encrypt (DNS-01 для wildcard) или сертификат хостинга.
3. Reverse proxy (Nginx/Caddy/Traefik): `server_name cms.sitename.ru` — CMS; `server_name qwenpaw.sitename.ru` — сервис.
4. В `.env` / compose: `PUBLIC_BASE_DOMAIN=sitename.ru`, `PUBLIC_PREFERRED_SCHEME=https`, порты `80`/`443` (без `:8080` в ссылках).

Реестр сервисов в CMS: `resources/cms/ui/menu.yaml` → секция `services`.
