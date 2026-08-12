FROM nginx:alpine

COPY . /app
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf