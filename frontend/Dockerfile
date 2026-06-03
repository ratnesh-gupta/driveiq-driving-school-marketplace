FROM node:24-bullseye

WORKDIR /app/frontend

RUN corepack enable && pnpm config set ignore-scripts false

COPY .npmrc .pnpmrc ./

RUN pnpm approve-builds esbuild 2>/dev/null || true

EXPOSE 5173
