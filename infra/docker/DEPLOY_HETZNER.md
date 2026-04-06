# Hetzner + Domain Deployment

This guide covers the path from:

- a Hetzner server
- a Namecheap-managed domain
- this repository

to a live HTTPS deployment.

## 1. Point the domain to the server

In Namecheap, open the domain and go to `Advanced DNS`.

If the domain uses `Namecheap BasicDNS`, create these records:

- `A` record with host `@` pointing to your Hetzner server IPv4
- `CNAME` record with host `www` pointing to `example.com`
- optional `AAAA` record with host `@` pointing to your server IPv6

Keep the TTL on `Automatic` unless you have a reason to change it.

Useful references:

- Namecheap DNS overview: https://www.namecheap.com/support/knowledgebase/article.aspx/319/2237/how-can-i-set-up-an-a-address-record-for-my-domain/

## 2. Verify DNS before deploying HTTPS

From your machine:

```bash
dig +short example.com
dig +short www.example.com
```

Both should resolve to your server before you start Caddy. HTTPS issuance depends on that.

## 3. Prepare the server

SSH into the server:

```bash
ssh root@<your-server-ip>
```

Install Docker, Git, and the Compose plugin:

```bash
apt update
apt install -y docker.io docker-compose-plugin git ufw
systemctl enable --now docker
```

Open the required ports:

```bash
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable
```

## 4. Upload the repository

```bash
mkdir -p /opt
cd /opt
git clone <your-repo-url> QParkingZones
cd /opt/QParkingZones
```

## 5. Configure the domain for Caddy

Create the deployment env file:

```bash
cp infra/docker/.env.https.example infra/docker/.env.https
```

Edit `infra/docker/.env.https` and set your real domain:

```bash
DOMAIN=example.com
WWW_DOMAIN=www.example.com
```

## 6. Start the HTTPS stack

```bash
docker compose \
  --env-file infra/docker/.env.https \
  -f infra/docker/docker-compose.https.yml \
  up -d --build
```

The stack layout is:

- `caddy` handles ports `80` and `443`
- `frontend` stays private inside Docker
- `frontend` proxies `/api/*` to `backend`
- `backend` keeps the SQLite database in a Docker volume

## 7. Check rollout

```bash
docker compose --env-file infra/docker/.env.https -f infra/docker/docker-compose.https.yml ps
docker compose --env-file infra/docker/.env.https -f infra/docker/docker-compose.https.yml logs -f caddy
docker compose --env-file infra/docker/.env.https -f infra/docker/docker-compose.https.yml logs -f frontend
docker compose --env-file infra/docker/.env.https -f infra/docker/docker-compose.https.yml logs -f backend
```

If DNS is correct, the app should come up at:

- `https://example.com`

## 8. Update later

```bash
cd /opt/QParkingZones
git pull
docker compose \
  --env-file infra/docker/.env.https \
  -f infra/docker/docker-compose.https.yml \
  up -d --build
```

## Notes

- Caddy automatically manages HTTPS certificates when the domain resolves to the server and ports `80` and `443` are reachable.
- The current backend runtime is still PHP's built-in server. That is acceptable for a lightweight deployment, but not the end state for a higher-traffic production system.
