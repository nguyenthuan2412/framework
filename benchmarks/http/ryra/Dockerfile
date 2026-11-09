# Build context = thư mục gốc repo. Image nền "ryra" build từ docker/php/8.5 (run.sh tự build).
FROM ryra

COPY . /var/www/html
COPY benchmarks/http/ryra/bench /var/www/html/modules/bench
COPY benchmarks/http/ryra/.env /var/www/html/.env
# php.ini của project (docker-compose gốc mount file này, ở đây copy vào image)
COPY docker/php/8.5/php.ini /usr/local/etc/php/php.ini

# Cấu hình production: không dev dependency, autoload tối ưu, bật OPcache cho CLI (Swoole chạy bằng CLI)
RUN mkdir -p storage/vietiso/logs \
    && composer install --no-dev --optimize-autoloader --no-interaction --quiet \
    && printf 'opcache.enable_cli=1\nopcache.validate_timestamps=0\n' > /usr/local/etc/php/conf.d/zz-bench.ini

# Cố định 4 worker; MAX_REQUEST cho phép tắt việc restart worker (mặc định framework: 100000)
# Cố định 4 worker cho cả server http và websocket (Node cluster cũng 4 worker)
RUN sed -i -E 's#// Constant::OPTION_WORKER_NUM => [^,]+,#Constant::OPTION_WORKER_NUM => (int) env("WORKERS", 4),#' config/app.php \
    && sed -i -E 's#Constant::OPTION_MAX_REQUEST => [0-9]+,#Constant::OPTION_MAX_REQUEST => (int) env("MAX_REQUEST", 100000),#' config/app.php \
    && grep -c 'OPTION_WORKER_NUM => (int)' config/app.php && grep -c 'OPTION_MAX_REQUEST => (int)' config/app.php

EXPOSE 8080
CMD ["php", "ryra", "start:serve"]
