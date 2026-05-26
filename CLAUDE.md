# CLAUDE.md - AI Assistant Guidelines for BPS MCPHost

Welcome! This file provides essential command guidelines, codebase contexts, and index policies for any AI agent (Antigravity, Claude Code, Cursor) working on the **BPS MCPHost** project.

---

## 🛠️ Project Command Reference

Always use these standard commands for building, testing, and running tasks in this project:

### 1. Development Environment
*   **Start dev services** (Docker containers, Vite dev server):
    ```bash
    ./start-dev.sh
    ```
*   **Analyze codebase structure**:
    ```bash
    ./analyze-codebase.sh
    ```

### 2. Testing & Benchmarking
*   **Run comprehensive BPS AI Agent automated test & metrics benchmark**:
    ```bash
    ./run_agent_tests.sh
    ```
    Or run the artisan command directly:
    ```bash
    php artisan bps:test-agent --region=denpasar
    ```
*   **Test with specific region/prompt**:
    ```bash
    php artisan bps:test-agent --region=mempawah --prompt="Tarik data IPM Mempawah terbaru"
    ```

### 3. Laravel Commands (inside Docker container if needed)
*   **Run migrations**:
    ```bash
    php artisan migrate
    ```
*   **Run seeders**:
    ```bash
    php artisan db:seed
    ```
*   **Run seeders for variable maps**:
    ```bash
    php artisan db:seed --class=BpsVariableMapSeeder
    ```

---

## 📊 CodeGraph Knowledge Index Policy

> [!IMPORTANT]
> **CodeGraph is initialized and fully up-to-date in this project (stored in `/.codegraph/`).**
> Any AI agent working on this project MUST prioritize CodeGraph queries over recursive grep/find loops to save context tokens, minimize tool calls, and speed up responses.

### 1. CLI Usage for AI Agents
When exploring symbols, tracing flows, or searching files, use the CLI directly:
*   **Search for a class or method**:
    ```bash
    codegraph query <SearchTerm>
    ```
*   **Map class/feature context**:
    ```bash
    codegraph context <FeatureName>
    ```
*   **Find callers of a function**:
    ```bash
    codegraph callers <FunctionName>
    ```
*   **Find callees of a function**:
    ```bash
    codegraph callees <FunctionName>
    ```
*   **Analyze code impact before edits**:
    ```bash
    codegraph impact <SymbolName>
    ```
*   **Identify affected test files**:
    ```bash
    git diff --name-only | codegraph affected --stdin
    ```

### 2. General Agent Interaction Strategy
1.  **Read First**: Treat the returned source signature from CodeGraph as authoritative. Do not re-read files unless verifying exact whitespace or local comments.
2.  **Route Mapping**: BPS MCPHost is a Laravel + Inertia/Vue app. Use CodeGraph to trace how a request flows from a route definition in `routes/web.php` or `routes/api.php` to its controller action and underlying service (e.g. `BpsApiService`).
3.  **Local Sync**: The index is auto-synced, but if you make large refactors, force a manual sync:
    ```bash
    codegraph sync
    ```
