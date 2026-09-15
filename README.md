# Pulse — test technique

Bienvenue. L'objectif : construire une petite API de mesures, **Pulse**, et la faire tourner sur un cluster K3s local.
Aucune vraie donnée de santé n'est utilisée — les appareils et les mesures sont entièrement fictifs.

Temps indicatif : **2h30 à 3h**. Prends le temps qu'il te faut, ce qui compte, c'est le résultat et ta capacité à
l'expliquer ensuite.

## Ce que l'API doit exposer

L'API est à développer en **PHP/Symfony**. Stockage en mémoire ou fichier — pas besoin d'une vraie base de données.

| Endpoint | Comportement attendu |
|---|---|
| `GET /health` | `200` si le service est prêt à recevoir du trafic |
| `POST /devices/{id}/readings` | Enregistre une mesure `{ "type": "...", "value": ..., "timestamp": "..." }` |
| `GET /devices/{id}/readings` | Liste les mesures enregistrées pour cet appareil |
| `DELETE /devices/{id}/readings/{readingId}` | Supprime une mesure |

### La règle métier

Chaque mesure retournée par `GET /devices/{id}/readings` doit porter un champ calculé `critical` (booléen), selon le `type` de la mesure :

| type | critique si |
|---|---|
| `temperature` | valeur `> 39` |
| `heart_rate` | valeur `> 120` |
| `oxygen_saturation` | valeur `< 90` |

Attention au sens de la comparaison : pour `oxygen_saturation`, c'est une valeur **basse** qui est critique.

## Authentification

Les routes `/devices/{id}/readings...` doivent être protégées par un token JWT. `GET /health` reste **public**
(le contrat K8s en dépend — les probes n'envoient pas de token).

- Header attendu : `Authorization: Bearer <token>`
- Algorithme : `HS256`, secret partagé via la variable d'environnement `JWT_SECRET` (déjà présente dans `k8s/configmap.yaml`, à reporter dans ton `.env` pour le dev local)
- Requête sans token, token invalide, ou token expiré → `401`

Utilise `firebase/php-jwt` (`composer require firebase/php-jwt:^7.0`) pour la vérification — l'objectif est de
brancher correctement la vérification dans Symfony, pas de réimplémenter du JWT à la main.

Pour développer et tester, un secret et un token valides te sont fournis :

```
JWT_SECRET=e070e6d4c2ab1ff06b428f5bc833b75fac92783236719e1f
```

Token à utiliser dans tes appels `curl` (`Authorization: Bearer <token>`) :

```
eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJwdWxzZS1jYW5kaWRhdGUiLCJpYXQiOjE3ODg1MzQ0NjMsImV4cCI6NDEwMjQ0NDgwMH0.zAE8ojECzgvxIYVE1IQs4gM0Tj0YW5PRVhqb5sjyFVE
```

## Bonus

Implémenter les notions qui ont été évoquées pendant l'entretien.

## Contrat technique (à respecter impérativement)

- L'application écoute sur le port **8080**
- `GET /health` répond `200` uniquement quand l'app est réellement prête (pas juste "le process a démarré")

Ce contrat est important : le manifeste Kubernetes fourni s'appuie dessus.

## Arborescence fournie

```
.
├── README.md          (ce fichier)
├── Dockerfile          (squelette à compléter)
└── k8s/
    ├── configmap.yaml
    ├── deployment.yaml
    └── service.yaml
```

Ton code applicatif suit l'arborescence standard d'un projet Symfony (`src/`, `public/`, `config/`...) — adapte le `Dockerfile` si besoin, notamment l'étape d'installation des dépendances.

## Déploiement local

Le déploiement doit se faire sur **K3s** (K3D — k3s packagé dans Docker — est la façon la plus simple d'en avoir un en local ; une installation k3s classique convient aussi).

1. Construis l'image : `docker build -t pulse-api:local .`
2. Importe l'image dans ton cluster K3s (ex. `k3d image import pulse-api:local -c <cluster>`)
3. `kubectl apply -f k8s/`
4. `kubectl get pods -w`

**Le manifeste fourni dans `k8s/` est volontairement imparfait.** Il est complet et se veut réaliste — mais quelque chose empêche le pod de passer `Ready`, ou le service de router le trafic correctement. C'est à toi de creuser (`kubectl describe`, `kubectl logs`, `kubectl get endpoints`...) et de corriger. Documente ce que tu as trouvé dans ton README.

## Livrables

- Le dépôt (code + `Dockerfile` + manifestes `k8s/` corrigés)
- Un README expliquant : tes choix techniques, comment lancer le tout, et ce qui n'allait pas dans le manifeste fourni (et comment tu l'as trouvé)
- Bonus, non éliminatoire : tests automatisés, pipeline CI minimal, chart Helm

Une fois le rendu fait, on prévoit un échange de ~30 minutes pour revenir ensemble sur tes choix.
