# Mini Réseau Social — Projet Fil Rouge Kubernetes

Mini application de type "réseau social" : un **CRUD de posts** avec persistance,
déployable sur **Kubernetes**.

| Couche   | Techno                          |
|----------|---------------------------------|
| Frontend | Vue.js 3 (Vite) servi par nginx |
| Backend  | Symfony 7 (API REST)            |
| BDD      | PostgreSQL 16 (avec PVC)        |
| Infra    | Kubernetes : Deployments, Services, Ingress, ConfigMap, Secret, PVC |

L'app permet de **créer / lire / modifier / supprimer** des messages (auteur +
contenu), de les "liker", le tout persisté en base PostgreSQL.

---

## Architecture

```
                       ┌──────────────── Ingress (social.local) ────────────────┐
                       │   /  ──────────────► frontend (nginx + Vue)            │
   navigateur ───────► │                                                        │
                       │   /api ────────────► backend  (Symfony API)            │
                       └───────────────────────────────┬────────────────────────┘
                                                        │
                                                        ▼
                                              postgres (PVC, persistance)
```

L'API expose :

| Méthode | Route             | Description              |
|---------|-------------------|--------------------------|
| GET     | `/api/health`     | Healthcheck              |
| GET     | `/api/posts`      | Liste des posts          |
| GET     | `/api/posts/{id}` | Détail d'un post         |
| POST    | `/api/posts`      | Créer un post            |
| PUT     | `/api/posts/{id}` | Modifier un post / liker |
| DELETE  | `/api/posts/{id}` | Supprimer un post        |

---

## 1. Tester en local (Docker Compose)

Le plus rapide pour vérifier que tout fonctionne avant Kubernetes :

```bash
docker compose up --build
```

Puis ouvrir **http://localhost:8080**.

---

## 2. Déployer sur Kubernetes (Minikube)

### Prérequis
- `minikube` et `kubectl` installés
- L'addon Ingress activé :

```bash
minikube start
minikube addons enable ingress
```

### Étape 1 — Construire les images dans le démon Docker de Minikube

Pour que Minikube trouve les images sans registry externe, on construit
directement dans son démon Docker.

**Linux / macOS :**
```bash
eval $(minikube docker-env)
docker build -t mini-social/backend:latest ./backend
docker build -t mini-social/frontend:latest ./frontend
```

**Windows (PowerShell) :**
```powershell
& minikube -p minikube docker-env --shell powershell | Invoke-Expression
docker build -t mini-social/backend:latest ./backend
docker build -t mini-social/frontend:latest ./frontend
```

> Les manifests utilisent `imagePullPolicy: IfNotPresent`, donc les images
> locales sont utilisées telles quelles.

### Étape 2 — Déployer les manifests

```bash
kubectl apply -k k8s/
# ou, un par un :
# kubectl apply -f k8s/
```

Vérifier le déploiement :
```bash
kubectl get pods,svc,ingress -n mini-social
```

Attendre que tous les pods soient `Running` / `Ready`.

### Étape 3 — Accéder à l'application

#### Cas Linux (driver docker ou VM) — l'IP du nœud est routable

```bash
echo "$(minikube ip) social.local" | sudo tee -a /etc/hosts
```
Puis ouvrir **http://social.local**

#### Cas Windows / macOS avec le driver **docker** — utiliser `minikube tunnel`

Avec le driver docker, l'IP du nœud (`192.168.49.2`) n'est pas routable depuis
l'hôte. L'Ingress est exposé sur `127.0.0.1` via un tunnel. Dans un terminal
**séparé** (laissé ouvert) :

```bash
minikube tunnel        # demande les droits admin
```

Puis pointer `social.local` vers `127.0.0.1` :

**Windows (PowerShell admin) :**
```powershell
Add-Content C:\Windows\System32\drivers\etc\hosts "127.0.0.1 social.local"
```
**macOS :**
```bash
echo "127.0.0.1 social.local" | sudo tee -a /etc/hosts
```

Puis ouvrir **http://social.local**

#### Alternative rapide sans Ingress (port-forward)

```bash
kubectl port-forward -n mini-social svc/frontend 8080:80
# puis http://localhost:8080
```

---

## 3. Vérifier la persistance

C'est l'objectif clé du TP. Créer quelques posts via l'interface, puis :

```bash
# Supprimer le pod backend : les données restent (elles sont en base)
kubectl delete pod -n mini-social -l app=backend

# Supprimer même le pod postgres : le PVC conserve les données sur disque
kubectl delete pod -n mini-social -l app=postgres
```

Après redémarrage des pods, les posts sont toujours là → la persistance via
**PVC** fonctionne.

---

## 4. Nettoyage

```bash
kubectl delete -k k8s/
# ou supprimer tout le namespace :
kubectl delete namespace mini-social
```

---

## 5. Pipeline CI/CD (GitHub Actions + runner self-hosted + GHCR)

À chaque `git push` sur `main`, la pipeline reconstruit **uniquement le
composant modifié** (backend ou frontend) puis :
**build → image (nouvelle version taggée par SHA) → push sur GHCR → rollout dans le cluster.**

Workflow : [`.github/workflows/deploy.yml`](.github/workflows/deploy.yml)

### Pourquoi un runner *self-hosted* ?

Le cluster est **local (Minikube)** : un runner GitHub hébergé dans le cloud ne
pourrait pas faire `kubectl rollout` sur ta machine. Le runner self-hosted
tourne sur ta machine, où il a accès à Docker **et** au cluster.

### Mise en place (une seule fois)

1. **Créer un dépôt GitHub** (ex. `mini-reseau-social`) puis pousser le code :
   ```bash
   git remote add origin https://github.com/<TON_USER>/mini-reseau-social.git
   git push -u origin main
   ```

2. **Installer le runner self-hosted** : sur GitHub, `Settings → Actions →
   Runners → New self-hosted runner → Windows x64`, puis suivre les commandes
   affichées (`config.cmd` avec le token fourni). Lancer ensuite le runner
   **avec ton utilisateur** (pour qu'il ait ton `~/.kube/config` et Docker) :
   ```powershell
   .\run.cmd
   ```
   > Le runner a besoin de `docker`, `kubectl` et `bash` (Git for Windows) dans
   > le PATH, et du contexte kube `filrouge`.

3. **Rendre les images GHCR accessibles au cluster** : après le 1er push
   réussi, les images apparaissent dans `Packages` sur GitHub. Les passer en
   **public** (`Package settings → Change visibility → Public`) pour que
   Minikube puisse les tirer. *(Alternative : créer un `imagePullSecret` avec un
   PAT `read:packages`.)*

### Démontrer la pipeline

```bash
# Modifier un composant, ex. le frontend
echo "<!-- test CI/CD -->" >> frontend/index.html
git commit -am "test: déclenche la pipeline frontend"
git push
```
👉 Onglet **Actions** sur GitHub : seul le job `deploy-frontend` s'exécute, et à
la fin le pod frontend est mis à jour (`kubectl get pods -n mini-social` montre
un nouveau pod). Le composant tourne alors depuis
`ghcr.io/<user>/mini-social-frontend:<sha>`.

### Points techniques (pièges rencontrés et résolus)

- **Shell du runner Windows** : `shell: bash` sélectionne le bash de **WSL**
  (`System32\bash.exe`) qui casse les chemins Windows. Le workflow utilise donc
  **PowerShell** pour toutes les étapes `run`.
- **Login GHCR** : le piping du token via `--password-stdin` en PowerShell
  échoue (`denied`). On utilise l'action officielle **`docker/login-action@v3`**.
- **Pull de l'image par Minikube** : par défaut, le builder Docker ajoute des
  **attestations SLSA** (`application/vnd.in-toto+json`) que le kubelet de
  Minikube refuse. Le build passe donc `--provenance=false --sbom=false`
  (+ `BUILDX_NO_DEFAULT_ATTESTATIONS=1`).
- **Visibilité des packages** : les images GHCR doivent être **publiques** pour
  que Minikube les tire sans secret (vérifiable : le pull anonyme réussit).

---

## 6. Monitoring (Prometheus + Grafana)

Stack **kube-prometheus-stack** installée via Helm dans le namespace
`monitoring` (valeurs : [`monitoring/values.yaml`](monitoring/values.yaml)).

### Installation

```bash
helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
helm repo update
helm install monitoring prometheus-community/kube-prometheus-stack \
  --namespace monitoring --create-namespace \
  -f monitoring/values.yaml
```

### Accéder à Grafana

```bash
kubectl port-forward -n monitoring svc/monitoring-grafana 3000:80
# http://localhost:3000  —  identifiants : admin / admin
```

Dashboard tout prêt pour la conso CPU/RAM des pods :
**Dashboards → Kubernetes / Compute Resources / Namespace (Pods)**, puis
sélectionner le namespace **`mini-social`**.

### Vérifier les métriques en ligne de commande (Prometheus)

```bash
kubectl port-forward -n monitoring svc/monitoring-kube-prometheus-prometheus 9090:9090
```
Requêtes PromQL (onglet Graph de http://localhost:9090) :
```promql
# RAM par pod du composant
sum by (pod) (container_memory_working_set_bytes{namespace="mini-social"})

# CPU (millicores) par pod du composant
1000 * sum by (pod) (rate(container_cpu_usage_seconds_total{namespace="mini-social"}[5m]))
```

---

## Structure du projet

```
mini-reseau-social/
├── .github/workflows/
│   └── deploy.yml      Pipeline CI/CD (build -> GHCR -> rollout)
├── monitoring/
│   └── values.yaml     Conf allégée kube-prometheus-stack
├── backend/            API Symfony (CRUD posts)
│   ├── src/            Entité Post, Repository, Controller
│   ├── config/         Config Symfony (Doctrine, CORS, routes)
│   ├── public/         Point d'entrée
│   ├── docker/         VirtualHost Apache + entrypoint
│   └── Dockerfile
├── frontend/           Application Vue.js
│   ├── src/            App.vue (UI CRUD) + api.js
│   ├── nginx.conf
│   └── Dockerfile
├── k8s/                Manifests Kubernetes
│   ├── 00-namespace.yaml
│   ├── 01-config.yaml      (ConfigMap + Secret)
│   ├── 02-postgres.yaml    (PVC + Deployment + Service)
│   ├── 03-backend.yaml
│   ├── 04-frontend.yaml
│   ├── 05-ingress.yaml
│   └── kustomization.yaml
└── docker-compose.yml  Stack locale de test
```

---

## Dépannage

### `ssh: handshake failed ... no supported methods remain`

La clé SSH stockée par Minikube ne correspond plus au nœud (fréquent après un
redémarrage de Docker Desktop). Correctif :

```bash
minikube delete --all --purge
minikube start --addons=ingress
```

Si la suppression échoue avec **« Access is denied »** sur
`...\.minikube\machines\minikube\id_rsa.pub` (fichier verrouillé par un
processus), deux options :

1. Redémarrer la machine puis relancer `minikube delete`, **ou**
2. Recréer le cluster sous un **autre nom de profil** (contourne le fichier
   verrouillé sans rien supprimer) :

   ```bash
   minikube start -p filrouge --driver=docker --addons=ingress
   # Toutes les commandes suivantes utilisent alors -p filrouge,
   # ou le docker-env : minikube -p filrouge docker-env
   ```

### Les endpoints `/api/posts` renvoient 500 (mais `/api/health` est OK)

La table `posts` n'a pas encore été créée. L'initContainer `db-migrate` du
déploiement backend s'en charge automatiquement ; vérifier ses logs :

```bash
kubectl logs -n mini-social -l app=backend -c db-migrate
```
