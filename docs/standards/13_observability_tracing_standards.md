# 13. Observabilitas & Structured Tracing Standards

Dokumen ini mendefinisikan standar observabilitas (*observability*) bertingkat untuk sistem **MCP Host & Government Analytics Portal**, mulai dari debugging lokal hingga roadmap distributed tracing produksi berbasis **OpenTelemetry** (standar industri Mei 2026).

---

## 🔭 Filosofi: Tiga Pilar Observabilitas

Standar industri 2026 mendefinisikan observabilitas sebagai kesatuan tiga sinyal yang saling melengkapi. Ketiga sinyal ini harus selalu berkorelasi melalui **`trace_id`** yang konsisten:

| Pilar | Apa yang Diukur | Tool di Proyek Ini |
|---|---|---|
| **Traces** | Alur eksekusi end-to-end (HTTP → Job → MCP → DB) | Laravel Telescope + `ai_agent` log channel |
| **Metrics** | Angka terukur (TTFB, error rate, token usage) | Horizon Dashboard + Log agregasi |
| **Logs** | Catatan kejadian berstruktur dengan konteks | Monolog JSON (`ai-agent.log`) |

---

## 🏗️ Arsitektur Observabilitas Proyek (3 Lapisan Pragmatis)

### Lapisan 1 — Laravel Telescope (Wajib di Lingkungan Lokal)

**Laravel Telescope** adalah dashboard debug real-time berbasis web yang merupakan standar bawaan ekosistem Laravel. Tersedia di `/telescope` tanpa infrastruktur tambahan.

**Kemampuan Telescope:**
- Semua **HTTP requests** (headers, payload, session, response time)
- Semua **database queries** dengan execution time dan query binding
- Semua **queued jobs** (`ProcessAiAgentQuery`) dengan status, payload, dan exception
- Semua **log entries** dengan level dan konteks
- Semua **exceptions** dengan stack trace lengkap
- Semua **WebSocket/Pusher events** yang di-broadcast

**Aturan Penggunaan:**
```php
// Telescope HANYA aktif di environment lokal/development
// Tidak pernah di-deploy ke produksi (potensi data leak)
// Dikonfigurasi via TelescopeServiceProvider dengan:
if ($this->app->isLocal()) {
    $this->app->register(TelescopeServiceProvider::class);
}
```

**Akses Dashboard:**
```
http://127.0.0.1:8900/telescope
```

---

### Lapisan 2 — Structured JSON Logging (Wajib di Semua Lingkungan)

Setiap eksekusi `ProcessAiAgentQuery` menghasilkan trace terstruktur lengkap di `storage/logs/ai-agent.log`.

#### Format Trace Entry Standar:
```json
// Event: Job Started
{
  "message": "ai_agent.job_started",
  "context": {
    "trace_id": "019e1234-abcd-7000-a123-bc29bef5d123",
    "chat_id": "019e505c-09fb-7227-a304-bc29bef5d623",
    "user_message_id": 42,
    "provider": "openai",
    "model": "gemini-3-flash",
    "mock_enforced": false
  },
  "level": 200,
  "level_name": "INFO",
  "datetime": "2026-05-22T15:44:31+07:00"
}

// Event: MCP Tools Loaded
{
  "message": "ai_agent.mcp_tools_loaded",
  "context": {
    "trace_id": "019e1234-abcd-7000-a123-bc29bef5d123",
    "chat_id": "...",
    "tool_count": 5,
    "tool_names": ["fetch_rapot_data", "get_bps_indicator", "compare_regencies"]
  }
}

// Event: Job Completed
{
  "message": "ai_agent.job_completed",
  "context": {
    "trace_id": "019e1234-abcd-7000-a123-bc29bef5d123",
    "chat_id": "...",
    "duration_ms": 873.57,
    "step_count": 2,
    "has_chart": true,
    "content_length": 1842
  }
}
```

#### Panduan Korelasi Log:
Gunakan `trace_id` untuk menelusuri satu permintaan pengguna di semua sinyal:
```bash
# Cari semua log dari satu eksekusi job tertentu
grep "019e1234-abcd-7000" storage/logs/ai-agent.log | jq .

# Atau dengan Docker:
docker compose exec web grep "trace_id_kamu" storage/logs/ai-agent.log | jq .
```

---

### Lapisan 3 — Log Channels yang Tersedia

| Channel | File | Tujuan |
|---|---|---|
| `stack` (default) | `laravel.log` | Log umum aplikasi |
| `json` | `laravel-json.log` | Log JSON terstruktur siap agregasi (produksi) |
| `ai_agent` | `ai-agent.log` | Trace khusus eksekusi AI job (JSON, rotasi 14 hari) |

**Penggunaan di kode:**
```php
// Log terstruktur ke channel ai_agent
Log::channel('ai_agent')->info('event_name', [
    'trace_id' => $traceId,
    'key'      => 'value',
]);

// Log umum ke channel default
Log::warning('Pesan sederhana');
```

---

## 🗺️ Roadmap: Migrasi ke OpenTelemetry Penuh (Produksi)

Jika sistem tumbuh ke skala produksi multi-server, implementasikan OpenTelemetry penuh:

### Stack Rekomendasi (Self-Hosted, Gratis):
```
[Laravel App + OTel PHP SDK]
       ↓ OTLP gRPC
[OpenTelemetry Collector]   ← filter, batch, route
       ↓
[SigNoz Community Edition]  ← UI all-in-one (Traces+Metrics+Logs)
       └── ClickHouse DB    ← storage cepat
```

### Package yang Dibutuhkan (Saat Siap):
```bash
# PHP OTel Extension (via PECL di Dockerfile)
pecl install opentelemetry

# Composer packages
composer require open-telemetry/opentelemetry-auto-laravel
composer require open-telemetry/sdk
```

### AI/LLM Semantic Conventions (Khusus Aplikasi AI):
Saat mengimplementasikan OTel penuh, gunakan **Generative AI Semantic Conventions** resmi OpenTelemetry untuk melacak:
- `gen_ai.usage.input_tokens` — Token prompt yang dikonsumsi
- `gen_ai.usage.output_tokens` — Token respons yang dihasilkan
- `gen_ai.request.model` — Model yang digunakan
- `gen_ai.response.finish_reason` — Alasan AI berhenti merespons

> [!IMPORTANT]
> Selalu aktifkan **content redaction** (`OTEL_INSTRUMENTATION_GENAI_CAPTURE_MESSAGE_CONTENT=false`) di produksi untuk mencegah kebocoran data prompt pengguna ke sistem monitoring.

---

## ✅ Checklist Kepatuhan

- [ ] `storage/logs/ai-agent.log` ada dan dapat ditulis oleh container
- [ ] Setiap panggilan `ProcessAiAgentQuery` menghasilkan minimal 2 entries (`job_started` & `job_completed`)
- [ ] `trace_id` unik UUID v4 dibuat di awal setiap job dan digunakan di semua log entries dalam job tersebut
- [ ] Laravel Telescope aktif dan dapat diakses di `/telescope` saat development
- [ ] Channel `ai_agent` dikonfigurasi dengan rotasi file 14 hari
