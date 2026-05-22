#!/bin/bash

EXCLUDE_DIRS="node_modules|vendor|\.git|storage|bootstrap/cache|public/storage|ui-references"
EXCLUDE_FILES="package-lock\.json|composer\.lock"

echo "=================================================="
echo "   MCPHost Gateway - Folder Structure & Lines     "
echo "=================================================="

# --- Kumpulkan semua line counts SEKALI SAJA ---
declare -A LINE_COUNT

while IFS= read -r -d '' file; do
    [[ "$file" =~ /($EXCLUDE_DIRS)(/|$) ]] && continue
    [[ "$(basename "$file")" =~ ^($EXCLUDE_FILES)$ ]] && continue
    LINE_COUNT["$file"]=$(wc -l < "$file" 2>/dev/null | tr -d ' ')
done < <(find . -type f -print0)

# --- Fungsi print tree (tidak ada subshell wc -l) ---
print_tree() {
    local dir="$1"
    local indent="$2"

    while IFS= read -r item; do
        [[ "$item" == "." || "$item" == ".." ]] && continue
        local path="$dir/$item"

        if [[ "$item" =~ ^($EXCLUDE_DIRS)$ ]]; then
            continue
        fi

        if [ -d "$path" ]; then
            echo "${indent}├── 📁 $item/"
            print_tree "$path" "${indent}│   "
        else
            [[ "$item" =~ ^($EXCLUDE_FILES)$ ]] && continue
            local lines="${LINE_COUNT[$path]:-0}"
            echo "${indent}├── 📄 $item ($lines baris)"
        fi
    done < <(ls -1a "$dir" 2>/dev/null | grep -v -E "^\.$|^\.\.$" | sort)
}

print_tree "." ""

echo ""
echo "=================================================="
echo "       10 BERKAS TERBESAR DI DALAM PROYEK         "
echo "=================================================="

# Pakai data LINE_COUNT yang sudah ada, tidak perlu find + wc lagi
for file in "${!LINE_COUNT[@]}"; do
    echo "${LINE_COUNT[$file]} $file"
done | sort -rn | head -n 10 | awk '{print "  " NR ". " $2 " (" $1 " baris)"}'

echo "=================================================="