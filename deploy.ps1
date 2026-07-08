# Script de déploiement Minikube (Windows / PowerShell)
# Usage : .\deploy.ps1
$ErrorActionPreference = "Stop"

Write-Host "==> Activation de l'addon ingress" -ForegroundColor Cyan
minikube addons enable ingress

Write-Host "==> Configuration du démon Docker de Minikube" -ForegroundColor Cyan
& minikube -p minikube docker-env --shell powershell | Invoke-Expression

Write-Host "==> Build de l'image backend" -ForegroundColor Cyan
docker build -t mini-social/backend:latest ./backend

Write-Host "==> Build de l'image frontend" -ForegroundColor Cyan
docker build -t mini-social/frontend:latest ./frontend

Write-Host "==> Déploiement des manifests Kubernetes" -ForegroundColor Cyan
kubectl apply -k k8s/

Write-Host "==> État du déploiement" -ForegroundColor Cyan
kubectl get pods,svc,ingress -n mini-social

Write-Host ""
Write-Host "Ajoutez '$(minikube ip) social.local' a votre fichier hosts, puis ouvrez http://social.local" -ForegroundColor Green
