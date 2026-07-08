#!/usr/bin/env bash
# Script de déploiement Minikube (Linux / macOS)
# Usage : ./deploy.sh
set -e

echo "==> Activation de l'addon ingress"
minikube addons enable ingress

echo "==> Configuration du démon Docker de Minikube"
eval "$(minikube docker-env)"

echo "==> Build de l'image backend"
docker build -t mini-social/backend:latest ./backend

echo "==> Build de l'image frontend"
docker build -t mini-social/frontend:latest ./frontend

echo "==> Déploiement des manifests Kubernetes"
kubectl apply -k k8s/

echo "==> État du déploiement"
kubectl get pods,svc,ingress -n mini-social

echo ""
echo "Ajoutez '$(minikube ip) social.local' a votre /etc/hosts, puis ouvrez http://social.local"
