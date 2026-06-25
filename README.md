# ⚡ FlexiCAF (Capacité À Faire)

FlexiCAF est une application web légère et performante dédiée au **pilotage de la capacité d'équipe (Capacity Planning)**. 

Conçue pour offrir une visibilité instantanée sur l'allocation des ressources, elle se base sur une architecture "Flat File" (fichiers JSON) qui la rend extrêmement facile à déployer, à sauvegarder et à migrer, sans nécessiter de base de données SQL.

## 🚀 Fonctionnalités Principales

* **Vues Croisées Dynamiques** : Analysez la charge (en Jours-Hommes) sous tous les angles via des tableaux de bord interactifs :
  * *Consultant / Mois* (Vue globale de l'équipe)
  * *Détail Consultant* (Focus sur un membre spécifique)
  * *Projet / Mois* (Suivi de l'effort déployé par projet)
  * *Projet / Consultant* (Matrice de répartition)
* **Heatmap Intelligente** : Coloration conditionnelle automatique des tableaux en fonction du ratio Charge / Capacité théorique (alerte en cas de surcharge).
* **Saisie Intuitive** : Ajout rapide de charge via un système de modale avec *Slider* (curseur) et support des décimales (points et virgules).
* **Analytique Avancée** : Génération de graphiques (via Chart.js) permettant de comparer la capacité théorique avec la charge réelle, filtrable par type d'activité (Build, Run, Absences...) ou par consultant.
* **Console d'Administration Complète** :
  * Gestion fine des utilisateurs et des rôles (Saisie pour soi, Saisie pour un tiers, Accès Dashboard, Admin activités).
  * Édition du catalogue de projets avec charte graphique pastel intégrée.
  * Éditeur JSON expert en temps réel (pour les utilisateurs avancés).
  * Générateur de sauvegarde intégrale (Archive ZIP des données et paramètres).
  * **Audit Log** : Traçabilité complète des 500 dernières actions effectuées (ajout, modification de charge, gestion des utilisateurs).

---

## 🏗️ Architecture et Fonctionnement

Le projet est divisé en deux parties principales :
* `src/` : Contient le code source de l'application (PHP 8, HTML5, CSS, JS, Bootstrap 5).
* `src/db/` : Dossier contenant la base de données sous forme de fichiers `.json` (`users.json`, `tasks.json`, `data.json`, `audit.json`, etc.). 

Cette séparation permet d'utiliser **Docker** de manière optimale : le code est scellé dans le conteneur, tandis que le répertoire `db/` est monté sur un volume hôte pour assurer la persistance et la portabilité des données.

---

## 🐳 Déploiement avec Docker (Local / Standalone)

L'application est conteneurisée à l'aide de l'image officielle PHP-Apache. 

### 1. Prérequis
* Docker installé sur la machine hôte.
* Le port `8080` disponible (modifiable selon vos besoins).

### 2. Compiler l'image locale
Placez-vous à la racine du projet (au même niveau que le `Dockerfile` et le dossier `src/`) et lancez la commande de compilation :

```bash
docker build -t flexicaf-app .
3. Démarrer le conteneur
Une fois l'image compilée, lancez le conteneur en mappant le dossier de base de données local vers le conteneur :

Bash
docker run -d \
  --name flexicaf \
  --restart unless-stopped \
  -p 8080:80 \
  -v "$(pwd)/src/db:/var/www/html/db" \
  flexicaf-app
Note sur les droits Linux : Si l'application affiche une erreur d'écriture JSON lors de la première utilisation, assurez-vous que l'utilisateur du conteneur (www-data, UID 33) a le droit d'écrire dans le dossier monté :

Bash
sudo chown -R 33:33 ./src/db
sudo chmod -R 775 ./src/db
⚙️ Premier lancement
Accédez à l'application via votre navigateur : http://localhost:8080 (ou l'IP de votre serveur).

L'application détectera qu'il s'agit du premier lancement (absence du fichier admin.json) et vous redirigera vers la page d'initialisation.

Définissez votre Mot de passe Super-Administrateur.

Connectez-vous avec l'identifiant admin et le mot de passe fraîchement créé.

Rendez-vous dans la Console d'Administration pour personnaliser le nom de votre équipe, créer vos collaborateurs et définir vos projets !

💾 Sauvegarde et Restauration
Puisque toute la donnée réside dans le dossier /src/db/, une simple copie de ce dossier suffit pour sauvegarder l'intégralité de l'outil.
Vous pouvez également, depuis la vue Administration, cliquer sur le bouton Archive ZIP pour télécharger instantanément un backup à jour de vos fichiers JSON.

Pour restaurer, il suffit d'écraser les fichiers du dossier /src/db/ avec ceux de votre sauvegarde.
