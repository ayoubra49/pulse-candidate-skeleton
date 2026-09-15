## Implémentation

L'API est construite avec Symfony 7.4 et PHP 8.3.

Le stockage utilise un fichier JSON local défini par la variable `DATA_PATH`.
Aucune base de données n'est nécessaire pour ce test.

Les responsabilités sont séparées :

- `FileReadingStore` gère la lecture, l'ajout, la liste et la suppression ;
- `CriticalityEvaluator` applique les règles de criticité ;
- `ReadingController` expose les endpoints HTTP ;
- `JwtAuthenticator` vérifie les tokens JWT avec l'algorithme HS256 ;
- `HealthController` vérifie que le stockage est disponible.

Les routes `/devices/...` nécessitent un token JWT.

La route `/health` reste publique afin de permettre aux probes Kubernetes de vérifier l'état réel de l'application.

## Lancement local

Configurer les variables locales :

```dotenv
APP_ENV=dev
DATA_PATH=var/pulse.json
JWT_SECRET=<secret fourni dans le sujet>
```

Installer les dépendances :

```bash
composer install
```

Lancer l'application :

```bash
php -S 127.0.0.1:8080 -t public
```

## Tests automatisés

Les tests fonctionnels sont lancés avec :

```bash
php bin/phpunit
```

Ils vérifient :

- l'accès public à `/health` ;
- la protection JWT des routes de mesures ;
- la création, la lecture et la suppression d'une mesure.

## Déploiement Kubernetes

Construire l'image :

```bash
docker build -t pulse-api:local .
```

Importer l'image dans K3d :

```bash
k3d image import pulse-api:local -c pulse
```

Déployer les manifestes :

```bash
kubectl apply -f k8s/
```

Vérifier le déploiement :

```bash
kubectl rollout status deployment/pulse-api
kubectl get pods -l app=pulse-api
kubectl get endpoints pulse-api
```

Pour tester localement le Service :

```bash
kubectl port-forward service/pulse-api 8080:80
```

L'application est alors accessible sur :

```text
http://127.0.0.1:8080
```

## Problèmes corrigés dans les manifestes

Les problèmes suivants étaient présents dans les fichiers fournis :

- le Dockerfile n'installait pas Composer ni les dépendances ;
- l'application écoutait sur le port `8080`, mais le Deployment utilisait `8000` ;
- les probes Kubernetes vérifiaient le port `8000` ;
- la liveness probe provoquait des redémarrages du conteneur ;
- le Service sélectionnait `app: pulse`, alors que les Pods utilisaient `app: pulse-api` ;
- le Service envoyait le trafic vers le port `8000` au lieu de `8080` ;
- `APP_ENV=dev` était incompatible avec `composer install --no-dev`.

Les erreurs ont été identifiées avec :

```bash
kubectl get pods
kubectl describe pod <nom-du-pod>
kubectl logs <nom-du-pod> --previous
kubectl get endpoints pulse-api
```

Le Pod final est `Ready`, ne redémarre plus et les probes `/health` fonctionnent sur le port `8080`.
