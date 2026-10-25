# platt-web: builds the Nuxt SPA, then serves landing (/) + app (/app/) via nginx,
# proxying /api/ to platt-api.
FROM node:22-alpine AS build
WORKDIR /web
RUN corepack enable && corepack prepare pnpm@9 --activate
COPY web/package.json web/pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile
COPY web/ ./
ENV NODE_OPTIONS=--max-old-space-size=1536
RUN pnpm run generate \
 # List every built app file so the service worker can precache the whole app.
 && node -e "const fs=require('fs'),p=require('path');const root='.output/public';const out=[];(function w(d){for(const f of fs.readdirSync(d)){const x=p.join(d,f);fs.statSync(x).isDirectory()?w(x):out.push('/app/'+p.relative(root,x).split(p.sep).join('/'))}})(root+'/_nuxt');fs.writeFileSync(root+'/precache.json',JSON.stringify(out))"

FROM nginx:alpine
COPY public /usr/share/nginx/html
COPY --from=build /web/.output/public /usr/share/nginx/html/app
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
