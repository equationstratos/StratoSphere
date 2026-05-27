# 🔐 Configuration SSL/TLS - StratoSphere

## Vue d'ensemble

Ce guide couvre la configuration SSL/HTTPS pour StratoSphere.

## Développement (Auto-signé)

### Généré automatiquement par install.sh

Le script `install.sh` génère automatiquement les certificats:

```bash
openssl req -x509 -newkey rsa:4096 \
  -keyout key.pem \
  -out cert.pem \
  -days 365 \
  -nodes \
  -subj "/C=FR/ST=State/L=City/O=StratoSphere/CN=localhost"
```

**Fichiers générés:**
- `key.pem` - Clé privée (4096 bits RSA)
- `cert.pem` - Certificat X.509 (365 jours)

### Vérifier les certificats

```bash
# Afficher les infos
openssl x509 -in cert.pem -text -noout

# Vérifier les dates
openssl x509 -in cert.pem -noout -dates

# Vérifier la clé
openssl rsa -in key.pem -check
```

## Production (Let's Encrypt - GRATUIT)

### Installer Certbot

```bash
sudo apt-get update
sudo apt-get install -y certbot python3-certbot-nginx
```

### Générer certificat

```bash
sudo certbot certonly --standalone -d your-domain.com
```

### Configurer StratoSphere

Modifier `server.js`:

```javascript
const serverConfig = {
    key: fs.readFileSync('/etc/letsencrypt/live/your-domain.com/private/key.pem'),
    cert: fs.readFileSync('/etc/letsencrypt/live/your-domain.com/fullchain.pem')
};
```

### Renouvellement auto

```bash
sudo crontab -e
# Ajouter:
0 0 1 * * certbot renew --quiet && systemctl restart stratosphere
```

## Dépannage

### Certificat manquant?

```bash
openssl req -x509 -newkey rsa:4096 -keyout key.pem -out cert.pem -days 365 -nodes
```

### Certificat expiré?

```bash
rm key.pem cert.pem
# Régénérer comme ci-dessus
```

