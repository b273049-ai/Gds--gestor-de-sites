FROM node:22-alpine
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --omit=dev
COPY server.js ./
ENV DATA_DIR=/app/dados
VOLUME ["/app/dados"]
EXPOSE 8080
CMD ["node", "server.js"]
