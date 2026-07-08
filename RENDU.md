# Rendu — Projet Fil Rouge Kubernetes

**Étudiant :** Adnan Mahboubi
**Application :** Mini Réseau Social — CRUD de posts avec persistance
**Stack :** Vue.js (front) · Symfony (API) · PostgreSQL (bdd) · Kubernetes (Minikube)

> Document de rendu. Insérer les captures d'écran aux emplacements indiqués
> `📸 [Capture : ...]` avant l'envoi.

---

## 1. Architecture générale

```
              Ingress (social.local)
              /            \
            /                \
        frontend            backend            postgres
        (Vue+nginx)        (Symfony API)      (PVC, persistance)
         2 replicas         2 replicas          1 replica
```

- **Frontend** : SPA Vue.js servie par nginx.
- **Backend** : API REST Symfony (`/api/posts` — CRUD complet).
- **BDD** : PostgreSQL avec **PersistentVolumeClaim** (données conservées).
- **Config** : `ConfigMap` (config non sensible) + `Secret` (mots de passe, DATABASE_URL).
- **Réseau** : `Ingress` externe route `/` → frontend et `/api` → backend.

📸 [Capture : `kubectl get all -n mini-social` montrant les 5 pods Running]

---

## 2. Fonctionnalité : CRUD + persistance

L'interface permet de créer, lire, modifier, supprimer et « liker » des posts.

📸 [Capture : l'interface web avec quelques posts]

**Preuve de persistance** (objectif clé) — les données survivent à la mort du pod BDD :
```bash
kubectl delete pod -n mini-social -l app=postgres   # on tue la base
# ... le pod est recréé par le Deployment ...
curl http://localhost:8080/api/posts                # les posts sont toujours là
```

📸 [Capture : liste des posts identique avant/après suppression du pod postgres]

---

## 3. Pipeline CI/CD

**Déclencheur :** `git push` sur `main`.
**Étapes :** build du composant → build de l'image (tag = SHA du commit) →
push sur **GHCR** (registry) → **rollout** du Deployment concerné.

- Outil : **GitHub Actions** avec un **runner self-hosted** (obligatoire car le
  cluster Minikube est local).
- Fichier : `.github/workflows/deploy.yml`.
- Mise à jour **par composant** : seul le dossier modifié (`backend/` ou
  `frontend/`) déclenche son job.

**Démonstration :** un push modifiant `frontend/` ne lance que `deploy-frontend`,
qui reconstruit l'image, la pousse sur GHCR et met à jour le pod frontend.

📸 [Capture : onglet Actions GitHub, run vert de la pipeline]
📸 [Capture : image publiée dans GHCR (onglet Packages)]
📸 [Capture : `kubectl get pods -n mini-social` avec le nouveau pod (AGE récent) après le rollout]

---

## 4. Monitoring

Stack **kube-prometheus-stack** (Prometheus + Grafana) dans le namespace
`monitoring`, installée via Helm.

Les métriques **CPU et RAM** de chaque pod du composant sont collectées par
Prometheus (via cAdvisor/kubelet) et visualisables dans Grafana.

Dashboard : *Kubernetes / Compute Resources / Namespace (Pods)* → namespace
`mini-social`.

Exemple de valeurs relevées (via Prometheus) :

| Pod | RAM | CPU |
|-----|-----|-----|
| backend  | ~31–33 MiB | ~1 millicore |
| frontend | ~10–12 MiB | ~0,1 millicore |
| postgres | ~37 MiB    | ~9 millicores |

📸 [Capture : dashboard Grafana CPU/RAM du namespace mini-social]

---

## 5. Compléments (non réalisés)

- **OAuth2/OIDC (Keycloak)** dans un namespace `security` : non réalisé.
- **Istio** : non réalisé.

---

## Annexe — commandes de vérification

```bash
kubectl get all -n mini-social            # application
kubectl get pvc -n mini-social            # persistance (Bound)
kubectl get ingress -n mini-social        # ingress
kubectl get pods -n monitoring            # stack monitoring
```
