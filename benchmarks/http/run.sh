#!/usr/bin/env bash
# Chạy benchmark: ./run.sh [framework...]   (mặc định: ryra express fastify gin)
# Tuỳ chỉnh: DURATION=15 CONNECTIONS=256 THREADS=2 RUNS=3 WARMUP=10 ./run.sh
set -euo pipefail
cd "$(dirname "$0")"

FRAMEWORKS=("$@")
[ $# -eq 0 ] && FRAMEWORKS=(ryra express fastify gin)
TESTS=(plaintext json db queries)
DURATION=${DURATION:-15}
CONNECTIONS=${CONNECTIONS:-256}
THREADS=${THREADS:-2}
RUNS=${RUNS:-3}
WARMUP=${WARMUP:-10}
OUT=results/$(date +%Y%m%d-%H%M%S)
mkdir -p "$OUT"

echo "==> Build image"
docker build -q -t ryra ../../docker/php/8.5 --build-arg COMPOSER_VERSION=2.11.1 >/dev/null
docker compose build -q
docker compose --profile tools build -q wrk

docker compose up -d --wait mysql >/dev/null

wrk() { docker compose run --rm -T wrk wrk "$@" 2>/dev/null; }

# Chuyển "1.23ms" / "850.00us" / "1.2s" -> ms
to_ms() { awk -v v="$1" 'BEGIN{ n=v+0; if (v~/us$/) n/=1000; else if (v~/ms$/) ; else if (v~/s$/) n*=1000; printf "%.2f", n }'; }

echo "| Framework | Test | Req/s | Latency avg (ms) | p99 (ms) | Errors |" > "$OUT/summary.md"
echo "|---|---|---:|---:|---:|---:|" >> "$OUT/summary.md"

for fw in "${FRAMEWORKS[@]}"; do
  echo "==> $fw"
  docker compose stop ryra express fastify gin >/dev/null 2>&1 || true
  docker compose up -d "$fw" >/dev/null
  # chờ server sẵn sàng
  until docker compose run --rm -T wrk curl -sf "http://$fw:8080/plaintext" >/dev/null 2>&1; do sleep 1; done

  for t in "${TESTS[@]}"; do
    url="http://$fw:8080/$t"
    wrk -t"$THREADS" -c"$CONNECTIONS" -d"${WARMUP}s" "$url" >/dev/null
    : > "$OUT/$fw-$t.txt"
    for i in $(seq "$RUNS"); do
      wrk -t"$THREADS" -c"$CONNECTIONS" -d"${DURATION}s" --latency "$url" | tee -a "$OUT/$fw-$t.txt" \
        | awk '/Requests\/sec/{rps=$2} /^    Latency/{avg=$2} /^ +99%/{p99=$2}
               /Non-2xx|Socket errors/{err=err" "$0} END{print rps, avg, p99, (err==""?"0":"x")}' >> "$OUT/$fw-$t.runs"
    done
    # median theo req/s
    read -r rps avg p99 err < <(sort -n "$OUT/$fw-$t.runs" | sed -n "$(( (RUNS + 1) / 2 ))p")
    errors=$(grep -E "Non-2xx|Socket errors" "$OUT/$fw-$t.txt" | sort -u | tr '\n' ';' || true)
    printf "| %s | %s | %.0f | %s | %s | %s |\n" "$fw" "$t" "$rps" "$(to_ms "$avg")" "$(to_ms "$p99")" "${errors:-0}" | tee -a "$OUT/summary.md"
  done
  docker compose stop "$fw" >/dev/null
done

docker compose down >/dev/null
echo
echo "Kết quả: $OUT/summary.md"
cat "$OUT/summary.md"
