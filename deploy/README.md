# Deployment

This project is a PHP 8.1+ and SQLite application. Production runs behind Nginx with PHP-FPM.

## Server bootstrap

Optional read-only audit before changing the server:

```bash
bash deploy/audit-server.sh
```

Run once on the ECS instance as `root`:

```bash
APP_DIR=/var/www/tian-system \
DEPLOY_USER=deploy \
DOMAIN=_ \
DEPLOY_PUBLIC_KEY='ssh-ed25519 ... github-actions-deploy-key' \
bash deploy/setup-server.sh
```

Use `DOMAIN=_` when the site is accessed directly by IP. Replace it with the real domain after DNS is ready.

## GitHub Actions secrets

Add these repository secrets:

| Secret | Value |
| --- | --- |
| `ALIYUN_HOST` | ECS public IP, for example `47.115.170.202` |
| `ALIYUN_PORT` | SSH port, usually `22` |
| `ALIYUN_USER` | Deploy user, usually `deploy` |
| `ALIYUN_SSH_KEY` | Private key whose public key is in `/home/deploy/.ssh/authorized_keys` |
| `ALIYUN_TARGET_DIR` | `/var/www/tian-system` |
| `PUBLIC_URL` | Optional health check URL, for example `http://47.115.170.202/` |

After this, every push to `main` runs PHP linting and deploys the latest code.

## Persistent data

The workflow intentionally does not overwrite:

- `storage/app.sqlite`
- `storage/uploads/`
- `storage/captcha/`

Back up `storage/app.sqlite` and `storage/uploads/` before major server maintenance.
