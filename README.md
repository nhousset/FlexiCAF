# FlexiCAF

# Arrêter le conteneur actuel
docker-compose down

# Reconstruire l'image avec l'extension zip et lancer en tâche de fond
docker-compose up -d --build


docker run -d \
  --name flexicaf_app \
  --restart unless-stopped \
  -p 8080:80 \
  -v "$(pwd)/src/db:/var/www/html/db" \
  flexicaf-image
