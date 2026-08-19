FROM node:24-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci

FROM node:24-alpine AS build
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .
ENV NODE_ENV=production
RUN npm run build

FROM node:24-alpine AS runtime
WORKDIR /app
ENV NODE_ENV=production
COPY --from=build /app/.next/standalone ./.next/standalone
COPY --from=build /app/.next/static ./.next/standalone/.next/static
COPY --from=build /app/.next/standalone/node_modules ./node_modules
COPY entry.js ./entry.js
COPY init-db.js ./init-db.js
EXPOSE 8080
CMD ["node", "entry.js"]