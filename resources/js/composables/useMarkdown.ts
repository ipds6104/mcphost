/**
 * useMarkdown — Composable untuk Markdown Rendering yang Aman (Standar Industri Mei 2026)
 *
 * Pipeline: markdownString → marked.parse() → DOMPurify.sanitize() → safe HTML
 *
 * Menggunakan:
 *  - `marked`    : Parser Markdown yang cepat & mendukung GitHub Flavored Markdown (GFM)
 *  - `DOMPurify` : Sanitizer HTML terpercaya untuk mencegah XSS — WAJIB digunakan
 *                  setelah parsing karena `marked` menghasilkan raw HTML yang bisa
 *                  mengandung payload berbahaya dari input pengguna.
 *
 * Referensi: https://marked.js.org | https://dompurify.com
 */
import { marked } from 'marked'
import DOMPurify from 'dompurify'

// Konfigurasi marked sekali saat module di-load
marked.setOptions({
  gfm: true,         // GitHub Flavored Markdown: tables, strikethrough, task lists
  breaks: true,      // Baris baru tunggal (Shift+Enter) → <br> (lebih natural untuk chat)
})

/**
 * Render sebuah string Markdown menjadi HTML yang aman (ter-sanitisasi).
 *
 * @param   {string} content - String Markdown mentah dari LLM
 * @returns {string}           HTML yang sudah di-sanitisasi, aman untuk v-html
 */
export function renderMarkdown(content: string): string {
  if (!content) return ''

  try {
    // Step 1: Parse Markdown → raw HTML (mungkin mengandung XSS)
    const rawHtml = marked.parse(content) as string

    // Step 2: Sanitize — buang semua payload berbahaya (script, onerror, dll.)
    return DOMPurify.sanitize(rawHtml, {
      // Izinkan atribut class & style untuk code highlighting
      ALLOWED_ATTR: ['class', 'style', 'href', 'target', 'rel', 'id', 'name'],
      // Pastikan link eksternal selalu dibuka di tab baru dengan aman
      FORCE_BODY: false,
      ADD_ATTR: ['target'],
    })
  } catch {
    // Fallback: kembalikan teks mentah jika parsing gagal
    return content
  }
}

/**
 * Composable Vue 3 — gunakan di dalam <script setup>
 *
 * @example
 * const { renderMarkdown } = useMarkdown()
 * // Lalu di template: v-html="renderMarkdown(message.content)"
 */
export function useMarkdown() {
  return { renderMarkdown }
}
