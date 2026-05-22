#!/bin/bash

# BPS-MCP AI - Instalasi Git Pre-Commit Hook
# Menyalin pre-commit script ke dalam direktori git hooks lokal dan mengatur izin eksekusi

HOOKS_DIR=".git/hooks"
PRE_COMMIT_HOOK="$HOOKS_DIR/pre-commit"

echo "=================================================="
echo "    BPS-MCP AI - INSTALASI GIT PRE-COMMIT HOOK    "
echo "=================================================="

if [ -d "$HOOKS_DIR" ]; then
    echo "Sedang memasang pre-commit hook..."
    cp scripts/pre-commit.sh "$PRE_COMMIT_HOOK"
    chmod +x "$PRE_COMMIT_HOOK"
    echo "✅ Git pre-commit hook berhasil dipasang dan diaktifkan!"
    echo "=================================================="
else
    echo "❌ ERROR: Direktori .git/hooks tidak ditemukan."
    echo "Pastikan Anda berada di root repositori Git proyek."
    echo "=================================================="
    exit 1
fi
