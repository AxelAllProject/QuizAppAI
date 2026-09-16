# Image de développement du frontend React (serveur Vite).
FROM node:24-alpine

WORKDIR /app

EXPOSE 5173

# Les node_modules vivent dans un volume : on les (ré)installe si package-lock.json a changé.
CMD ["sh", "-c", "cmp -s package-lock.json node_modules/.package-lock.json || npm ci; npm run dev -- --host 0.0.0.0"]
