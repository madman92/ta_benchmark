FROM node:24-alpine
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npx tsc
ENV NODE_ENV=production
EXPOSE 8080
CMD ["node", "dist/main.js"]
