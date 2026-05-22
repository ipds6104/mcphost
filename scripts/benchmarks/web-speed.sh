#!/bin/bash

# Web Speed Benchmark Script for MCPHost Gateway
# Measures HTTP performance metrics: TTFB, DNS lookup, Connect Time, and Total Time.

# Text formatting colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}==================================================${NC}"
echo -e "${BLUE}    MCPHost Gateway - Web Speed Benchmark Tool    ${NC}"
echo -e "${BLUE}==================================================${NC}"
echo ""

# Default targets
TARGET_WEB="http://127.0.0.1:8900"
TARGET_VITE="http://127.0.0.1:5173"
NUM_REQUESTS=5

# Curl format configuration
CURL_FORMAT='{
  "time_namelookup": %{time_namelookup},
  "time_connect": %{time_connect},
  "time_pretransfer": %{time_pretransfer},
  "time_starttransfer": %{time_starttransfer},
  "time_total": %{time_total},
  "http_code": %{http_code}
}'

run_benchmark() {
    local label=$1
    local url=$2
    echo -e "${YELLOW}Benchmarking ${label} (${url})...${NC}"
    echo -e "Running ${NUM_REQUESTS} requests to gather metrics..."
    echo -e "--------------------------------------------------"
    
    local sum_dns=0
    local sum_conn=0
    local sum_pre=0
    local sum_ttfb=0
    local sum_total=0
    local count=0

    for ((i=1; i<=NUM_REQUESTS; i++)); do
        # Run curl and output json
        local response=$(curl -s -o /dev/null -w "$CURL_FORMAT" "$url")
        
        # Parse values using grep/awk for cross-platform compatibility (no jq dependency required)
        local dns=$(echo "$response" | grep -oP '"time_namelookup": \K[0-9.]+')
        local conn=$(echo "$response" | grep -oP '"time_connect": \K[0-9.]+')
        local pre=$(echo "$response" | grep -oP '"time_pretransfer": \K[0-9.]+')
        local ttfb=$(echo "$response" | grep -oP '"time_starttransfer": \K[0-9.]+')
        local total=$(echo "$response" | grep -oP '"time_total": \K[0-9.]+')
        local code=$(echo "$response" | grep -oP '"http_code": \K[0-9]+')

        # Convert to milliseconds for readability
        local dns_ms=$(echo "$dns * 1000" | bc 2>/dev/null || awk "BEGIN {print $dns * 1000}")
        local conn_ms=$(echo "$conn * 1000" | bc 2>/dev/null || awk "BEGIN {print $conn * 1000}")
        local pre_ms=$(echo "$pre * 1000" | bc 2>/dev/null || awk "BEGIN {print $pre * 1000}")
        local ttfb_ms=$(echo "$ttfb * 1000" | bc 2>/dev/null || awk "BEGIN {print $ttfb * 1000}")
        local total_ms=$(echo "$total * 1000" | bc 2>/dev/null || awk "BEGIN {print $total * 1000}")

        echo -e "Req #$i: HTTP $code | DNS: ${dns_ms}ms | Conn: ${conn_ms}ms | TTFB: ${ttfb_ms}ms | Total: ${total_ms}ms"

        # Accumulate sums using awk for floating point calculations
        sum_dns=$(awk "BEGIN {print $sum_dns + $dns_ms}")
        sum_conn=$(awk "BEGIN {print $sum_conn + $conn_ms}")
        sum_pre=$(awk "BEGIN {print $sum_pre + $pre_ms}")
        sum_ttfb=$(awk "BEGIN {print $sum_ttfb + $ttfb_ms}")
        sum_total=$(awk "BEGIN {print $sum_total + $total_ms}")
        count=$((count+1))
    done

    # Calculate averages
    local avg_dns=$(awk "BEGIN {print $sum_dns / $count}")
    local avg_conn=$(awk "BEGIN {print $sum_conn / $count}")
    local avg_pre=$(awk "BEGIN {print $sum_pre / $count}")
    local avg_ttfb=$(awk "BEGIN {print $sum_ttfb / $count}")
    local avg_total=$(awk "BEGIN {print $sum_total / $count}")

    echo -e "--------------------------------------------------"
    echo -e "${GREEN}AVERAGE METRICS FOR ${label}:${NC}"
    printf "  - DNS Lookup:        %.2f ms\n" "$avg_dns"
    printf "  - TCP Connection:    %.2f ms\n" "$avg_conn"
    printf "  - Time to First Byte (TTFB): %.2f ms (Server response latency)\n" "$avg_ttfb"
    printf "  - Total Request Time:%.2f ms\n" "$avg_total"
    echo ""
}

# Run benchmarks
run_benchmark "Laravel Web Server" "$TARGET_WEB"
run_benchmark "Vite Dev Server" "$TARGET_VITE"

echo -e "${BLUE}==================================================${NC}"
echo -e "${GREEN}Benchmark Complete!${NC}"
echo -e "💡 ${YELLOW}Tips to speed up local load times:${NC}"
echo -e "1. Vite compiles assets on demand during first load. Subsequent loads are instantaneous."
echo -e "2. Use 127.0.0.1 instead of 'localhost' to bypass Windows DNS/IPv6 lookup delays."
echo -e "3. In production Coolify setups, OPcache is enabled which drastically improves TTFB."
echo -e "${BLUE}==================================================${NC}"
