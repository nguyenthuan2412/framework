FROM node:26-alpine
WORKDIR /app
ENV NODE_ENV=production
COPY package.json ./
RUN npm install --omit=dev --no-audit --no-fund && npm ls --depth=0
COPY *.js ./
EXPOSE 8080
