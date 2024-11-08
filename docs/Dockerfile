FROM nginx AS base
ARG CI_ENVIRONMENT_NAME=development
ENV APPLICATION_ENV=$CI_ENVIRONMENT_NAME

FROM base AS production
COPY ./src/.vuepress/dist /usr/share/nginx/html
COPY ./deploy/nginx.conf /etc/nginx/conf.d/default.conf

FROM production AS staging
FROM production AS testing

FROM base AS development
RUN apt update; \
    apt -y install npm iputils-ping traceroute vim procps docker.io;