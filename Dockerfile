FROM node:22-alpine AS build
WORKDIR /app
COPY server/package.json server/package-lock.json ./
RUN npm ci
COPY server/tsconfig.json ./
COPY server/src ./src
RUN npm run build && npm prune --omit=dev

FROM node:22-alpine
WORKDIR /app
RUN addgroup -S wpagent && adduser -S wpagent -G wpagent \
  && mkdir -p /app/data && chown -R wpagent:wpagent /app
COPY --from=build --chown=wpagent:wpagent /app/node_modules ./node_modules
COPY --from=build --chown=wpagent:wpagent /app/package.json ./
COPY --from=build --chown=wpagent:wpagent /app/dist ./dist
USER wpagent
ENV NODE_ENV=production \
    WPAGENT_HTTP=1 \
    WPAGENT_HTTP_HOST=0.0.0.0 \
    WPAGENT_HTTP_PORT=3333 \
    WPAGENT_SESSION_STORE=file \
    WPAGENT_SESSION_DIR=/app/data
EXPOSE 3333
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s \
  CMD node -e "fetch('http://127.0.0.1:3333/healthz').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"
CMD ["node", "dist/index.js", "--http"]
