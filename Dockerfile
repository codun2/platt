# platt-web: builds the Nuxt SPA, then serves landing (/) + app (/app/) via nginx,
# proxying /api/ to platt-api.
FROM node:22-alpine AS build
WORKDIR /web
RUN corepack enable && corepack prepare pnpm@9 --activate
COPY web/package.json web/pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile
COPY web/ ./
ENV NODE_OPTIONS=--max-old-space-size=1536
RUN pnpm run generate

FROM nginx:alpine
COPY public /usr/share/nginx/html
COPY --from=build /web/.output/public /usr/share/nginx/html/app
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
