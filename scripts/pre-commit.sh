#!/bin/bash

# BPS-MCP Git Pre-Commit Hook
# Menjalankan verifikasi otomatis pada Backend & Frontend sebelum melakukan Git Commit

echo "=================================================="
echo "    BPS-MCP AI - GIT PRE-COMMIT HOOK LINTING      "
echo "=================================================="

# 1. Verifikasi PHP Backend (Laravel Pint)
echo "🔍 Menjalankan pemeriksaan format Laravel Pint..."
if [ -f "./vendor/bin/pint" ]; then
    if ! php vendor/bin/pint --test; then
        echo "❌ ERROR: Pemeriksaan format Laravel Pint gagal!"
        echo "Silakan rapikan kode PHP Anda dengan menjalankan perintah: php vendor/bin/pint"
        exit 1
    fi
else
    echo "⚠️ Warning: ./vendor/bin/pint tidak ditemukan. Lewati pemeriksaan PHP."
fi
echo "✅ Pengujian format Backend (PHP) berhasil."
echo "--------------------------------------------------"

# 2. Verifikasi Frontend Linting (ESLint)
echo "🔍 Menjalankan pemeriksaan sintaks ESLint..."
if ! bun run lint; then
    echo "❌ ERROR: Pemeriksaan ESLint gagal!"
    echo "Silakan perbaiki kesalahan ESLint/TypeScript pada frontend."
    exit 1
fi
echo "✅ Pengujian sintaks Frontend (ESLint) berhasil."
echo "--------------------------------------------------"

# 3. Verifikasi Kompilasi Produksi (Vite production build)
echo "🔍 Menjalankan kompilasi produksi Vite untuk verifikasi type-safety..."
if ! bun run build; then
    echo "❌ ERROR: Kompilasi produksi build Vite gagal!"
    echo "Silakan perbaiki kesalahan type-safety TypeScript dan coba komit kembali."
    exit 1
fi
echo "✅ Kompilasi produksi Frontend berhasil."
echo "=================================================="
echo "🎉 SELURUH PENGUJIAN BERHASIL! Prosedur Komit Dilanjutkan."
echo "=================================================="
exit 0
