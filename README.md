---

# ICORE Tax Gateway

![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?style=for-the-badge&logo=mysql)
![Redis](https://img.shields.io/badge/Redis-6.0%2B-DC382D?style=for-the-badge&logo=redis)
![Postman](https://img.shields.io/badge/Postman-FF6C37?style=for-the-badge&logo=postman)
![Nginx](https://img.shields.io/badge/Nginx-009639?style=for-the-badge&logo=nginx)

---

## Table of Contents

1.  [Introduction](#1-introduction)
2.  [Features](#2-features)
3.  [KRA eTIMS Compliance](#3-kra-etims-compliance)
4.  [Architecture & Design Principles](#4-architecture--design-principles)
5.  [Prerequisites](#5-prerequisites)
6.  [Installation Guide](#6-installation-guide)
    *   [6.1. Clone Repository](#61-clone-repository)
    *   [6.2. Laragon Setup](#62-laragon-setup)
    *   [6.3. Project Dependencies](#63-project-dependencies)
    *   [6.4. Database & Seeding](#64-database--seeding)
    *   [6.5. Environment Configuration](#65-environment-configuration)
7.  [Configuration](#7-configuration)
8.  [API Usage (For POS/ERP Integrators)](#8-api-usage-for-poserp-integrators)
    *   [8.1. Authentication](#81-authentication)
    *   [8.2. API Endpoints](#82-api-endpoints)
    *   [8.3. Error Handling](#83-error-handling)
    *   [8.4. Postman Collection](#84-postman-collection)
9.  [KRA Integration Modes (OSCU Direct vs. VSCU JAR)](#9-kra-integration-modes-oscu-direct-vs-vscu-jar)
10. [Testing](#10-testing)
    *   [10.1. Unit & Feature Tests](#101-unit--feature-tests)
    *   [10.2. Live Sandbox Testing](#102-live-sandbox-testing)
11. [Web UI (Client Portal)](#11-web-ui-client-portal)
12. [Troubleshooting Common Issues](#12-troubleshooting-common-issues)
13. [Future Enhancements](#13-future-enhancements)
14. [License](#14-license)

---

## 1. Introduction

The **ICORE Tax Gateway** is a robust, API-driven middleware solution designed to streamline compliance with the Kenya Revenue Authority's (KRA) eTIMS (Electronic Tax Invoice Management System). It acts as an abstraction layer, allowing Point of Sale (POS) and Enterprise Resource Planning (ERP) systems to seamlessly integrate with KRA eTIMS via a clean, RESTful JSON API, without needing to handle the complexities of KRA's underlying XML protocols or specific communication nuances.

Developed with a "developer-first" approach, the Gateway simplifies eTIMS integration, reduces development effort, and ensures accurate, real-time tax compliance for businesses operating in Kenya.

## 2. Features

The ICORE Tax Gateway provides the following core functionalities:

*   **KRA Device Management:** Register and activate KRA eTIMS devices (OSCU/VSCU) and retrieve their operational status.
*   **Item Master Data Management:** Perform CRUD (Create/Read) operations for product/service item master data, keeping KRA informed of product catalogs.
*   **Purchase Data Management:** Send and retrieve purchase transaction details to/from KRA, supporting compliance for inbound goods/services.
*   **Inventory Movement Management:** Report stock-in and stock-out movements to KRA for accurate inventory tracking and reconciliation.
*   **Sales & Credit Note Transaction Processing:**
    *   **Synchronous Signing:** Real-time submission of sales invoices and credit notes to the KRA device for immediate digital signing and QR code generation.
    *   **Asynchronous Journaling:** Reliable background submission of detailed line-item data and transaction journals to KRA's central API, ensuring high API responsiveness.
*   **Report Management:** Generate and retrieve various KRA-mandated financial reports (X Daily, Z Daily, PLU reports) for compliance and auditing.
*   **Comprehensive Error Handling:** Translate cryptic KRA error codes into clear, actionable JSON responses.
*   **Web-based Client UI:** A secure web portal for taxpayers to manage API keys, monitor device status, and generate compliance reports.

## 3. KRA eTIMS Compliance

The ICORE Tax Gateway strictly adheres to the technical specifications outlined in KRA's official eTIMS documentation, including:

*   "Technical Specification of Trader Invoicing System (TIS) for Online and Virtual Sales Control Unit (OSCU & VSCU) v2.0" (Doc 1)
*   "eTIMS Online Sales Control Unit (OSCU) AND Virtual Sales Control Unit (VSCU) Step-by-Step Guide" (Doc 2)
*   "VIRTUAL SALES CONTROL UNIT REQUIREMENTS & COMMUNICATION PROTOCOLS Version 2.0" (Doc 4 - **This is the primary specification for VSCU integration**).

The system is designed to handle both **OSCU direct integration** (sending JSON to KRA's direct API, based on observed live sandbox behavior) and **VSCU JAR integration** (sending XML to a local VSCU JAR, as per Doc 4).

## 4. Architecture & Design Principles

The Gateway is built on a robust Laravel 11 (PHP 8.2+) application, leveraging:

*   **RESTful JSON API:** External communication uses clean JSON payloads over standard HTTP methods.
*   **Internal KRA Protocol Translation:** The Gateway intelligently translates JSON requests to KRA's expected XML or JSON formats, and vice-versa, depending on the integration mode (OSCU direct vs. VSCU JAR).
*   **Layered Service Architecture:** Business logic is encapsulated in dedicated services (`KraApi`, `KraDeviceService`, `KraSalesService`, etc.) for maintainability.
*   **Asynchronous Processing:** Laravel Queues (with Redis) handle background tasks (e.g., detailed journaling to KRA's central API), ensuring API responsiveness.
*   **MySQL Database:** For internal data persistence (API clients, devices, transactions). PostgreSQL is recommended for production.
*   **Nginx:** High-performance web server.
*   **Laravel Breeze:** For robust web UI authentication scaffolding.

## 5. Prerequisites

Before you begin, ensure you have the following installed on your Windows machine:

*   **Laragon Full:** This bundles Nginx, PHP, MySQL, and Redis. Download from [laragon.org](https://laragon.org/download/index.html).
*   **Node.js & npm:** Download from [nodejs.org](https://nodejs.org/).
*   **Git:** For version control.

**For VSCU JAR Integration (If KRA provides the JAR):**
*   **Java Development Kit (JDK) 16+:** Download from Oracle or AdoptOpenJDK.
*   **eTIMS VSCU JAR File:** Obtain this from KRA's eTIMS portal after successful VSCU solution activation. (Currently a known blocking point if download is not yet active).

## 6. Installation Guide

Follow these steps to get the ICORE Tax Gateway running on your local machine.

### 6.1. Clone Repository

1.  Navigate to your Laragon `www` directory: `cd C:\laragon\www\`
2.  Clone the project:
    ```bash
    git clone [your_repo_url] icore-tax-gateway
    cd icore-tax-gateway
    ```

### 6.2. Laragon Setup

1.  **Launch Laragon.**
2.  **Stop All Services:** Click **"Stop All"**.
3.  **Configure PHP Version & Extensions:**
    *   `Menu > PHP > Version`: Select **PHP 8.2** (or your specific Laravel 11 compatible version).
    *   `Menu > PHP > Extensions`: Ensure `pdo_mysql`, `redis`, `xml`, `dom`, `curl`, `mbstring`, `json` are **checked**. **Uncheck `pdo_pgsql`**.
    *   `Menu > PHP > php.ini`:
        *   Set `display_errors = On`, `display_startup_errors = On`, `error_reporting = E_ALL`, `log_errors = On`.
        *   Set `error_log = C:\laragon\tmp\php_error.log` (or a clear path).
        *   Set `date.timezone = UTC`.
        *   Save and close.
4.  **Configure Nginx:**
    *   `Menu > Nginx`: Ensure **`nginx` is CHECKED** (and `apache` is NOT checked).
    *   Verify `C:\laragon\etc\nginx\sites-enabled\auto.icore-tax-gateway.test.conf` exists and contains the clean Nginx configuration as specified in the project's codebase (or the template in the `README`'s previous iteration).
5.  **Start All Services:** Click **"Start All"**. Verify Nginx, PHP-FPM, MySQL, Redis are running.
6.  **Verify Virtual Host:** Click "Website". `http://icore-tax-gateway.test` should show the Laravel Welcome Page.

### 6.3. Project Dependencies

1.  **Open Laragon Terminal:** Click the Terminal icon in Laragon.
2.  **Navigate to project root:** `cd C:\laragon\www\icore-tax-gateway`.
3.  **Install Composer Dependencies:**
    ```bash
    composer install
    ```
4.  **Install Node.js Dependencies & Compile Assets (for UI):**
    ```bash
    npm install
    npm run dev # for development assets (watches for changes)
    ```

### 6.4. Database & Seeding

1.  **Create MySQL Database:**
    *   `Menu > MySQL > Create New Database`.
    *   Enter `icore_gateway` for the name. Click OK.
2.  **Generate Application Key:**
    ```bash
    php artisan key:generate
    ```
3.  **Run Migrations and Seeders:**
    ```bash
    php artisan migrate:fresh --seed
    ```
    *   **IMPORTANT:** Watch the terminal output during seeding! The `ApiClientSeeder` will print the **plain-text API Keys** for the POS, ERP, and UI Backend clients. **COPY THESE KEYS!** You will need them for `.env` and Postman.

### 6.5. Environment Configuration

1.  **Open `C:\laragon\www\icore-tax-gateway\.env`.**
2.  **Update Database & Redis:** Ensure `DB_` and `REDIS_` settings point to `127.0.0.1` and respective ports (`3306`, `6379`).
3.  **Update KRA/VSCU Configuration:**
    *   Set `KRA_API_SANDBOX_BASE_URL="https://etims-api-sbx.kra.go.ke/etims-api/"` (This is for OSCU direct integration).
    *   Set `KRA_VSCU_JAR_BASE_URL="http://127.0.0.1:8088"` (Where your VSCU JAR will run, even if it's currently mocked).
    *   Set `KRA_SIMULATION_MODE=true` for local development. **When you get the VSCU JAR or definitive OSCU sandbox access, you'll change this to `false`.**
    *   Set `KRA_QR_CODE_BASE_URL` and `KRA_INVOICE_VERIFICATION_BASE_URL` to the actual KRA URLs as provided in the documentation.
4.  **Update ICORE Specific Keys:**
    *   `ICORE_API_KEY_HEADER="X-API-Key"`
    *   `ICORE_UI_BACKEND_API_KEY="[YOUR_UI_BACKEND_API_KEY_FROM_SEEDER]"` (Paste the key copied from the seeder output).
5.  **Save `.env`.**
6.  **Clear Laravel Cache & Restart Laragon:**
    *   `php artisan route:clear`
    *   `php artisan cache:clear`
    *   `php artisan config:clear`
    *   **Stop All Laragon Services**, then **Start All**.

---

## 7. Configuration

Key configuration files include:

*   `config/app.php`: Main Laravel application settings.
*   `config/database.php`: Database connection details.
*   `config/queue.php`: Queue driver settings (Redis).
*   `config/logging.php`: Logging channels and levels.
*   `config/kra.php`: Specific KRA API URLs, timeouts, and simulation settings.
*   `config/icore.php`: ICORE-specific application settings (e.g., internal API keys).

**Note:** Production environments will use environment variables directly, managed by secret management systems, rather than `.env` files.

## 8. API Usage (For POS/ERP Integrators)

The Gateway exposes a clean, RESTful JSON API. All endpoints are prefixed with `/api/v1/`.

### 8.1. Authentication

*   **Method:** API Key authentication.
*   **Header:** `X-API-Key: [YOUR_API_KEY]` (Replace `[YOUR_API_KEY]` with the plain-text key obtained from seeding).
*   **Authorization:** Each API Key is tied to specific `taxpayerPin`s. Requests must specify `gatewayDeviceId` and `taxpayerPin` in the payload, and the API Key must be authorized for them.

### 8.2. API Endpoints

(Refer to the Postman Collection for full request bodies and examples)

*   **Device Management:**
    *   `POST /api/v1/devices/initialize`: Initialize/activate KRA device (OSCU/VSCU).
    *   `GET /api/v1/devices/{gatewayDeviceId}/status`: Get current status of a KRA device.
*   **Item Management:**
    *   `POST /api/v1/items`: Register or update an item with KRA.
    *   `GET /api/v1/items`: Retrieve list of items from KRA.
*   **Purchase Management:**
    *   `POST /api/v1/purchases`: Send purchase data (header + items) to KRA.
    *   `GET /api/v1/purchases`: Get purchase data from KRA.
*   **Inventory Management:**
    *   `POST /api/v1/inventory/movements`: Send inventory stock movements to KRA.
*   **Sales & Credit Note Transaction Processing:**
    *   `POST /api/v1/transactions`: Process a sales or credit note transaction (synchronous signing + asynchronous journaling).
*   **Report Management:**
    *   `GET /api/v1/reports/x-daily`: Get X Daily Report.
    *   `POST /api/v1/reports/z-daily`: Generate Z Daily Report.
    *   `GET /api/v1/reports/plu`: Get PLU Report.

### 8.3. Error Handling

*   **HTTP Status Codes:** Standard (200, 400, 401, 403, 404, 422, 500, 503).
*   **JSON Format:**
    ```json
    {
      "timestamp": "ISO 8601",
      "status": 4xx/5xx,
      "error": "HTTP Status Name",
      "message": "Human-readable error description.",
      "gatewayErrorCode": "ICORE_SPECIFIC_CODE | KRA_SPECIFIC_CODE",
      "details": { /* optional: kraErrorCode, kraRawResponse, fieldErrors */ },
      "traceId": "unique-request-id"
    }
    ```

### 8.4. Postman Collection

A comprehensive Postman Collection covering all API endpoints is provided.
*   **Import:** Use the JSON file provided separately.
*   **Variables:** Configure your `baseUrl`, `apiKey`, `taxpayerPin`, `gatewayDeviceId` in the Postman Environment.
*   **Pre-request Scripts:** Essential for dynamic date/time formatting. Ensure scripts for `kraFormattedTimestamp` and `dateYMD` are correctly applied to relevant requests.

## 9. KRA Integration Modes (OSCU Direct vs. VSCU JAR)

The Gateway is designed for flexibility to handle KRA's evolving integration models.

*   **OSCU Direct Integration (Current Sandbox Reality):**
    *   **Scenario:** If KRA expects direct API calls from your system (like an Online Sales Control Unit).
    *   **Communication:** Our Gateway sends **JSON payloads** to `KRA_API_SANDBOX_BASE_URL` (e.g., `https://etims-sbx.kra.go.ke/etims-api/`).
    *   **Gateway Configuration:** `KRA_SIMULATION_MODE=false` and `KraDevice.device_type='OSCU'`.
    *   **Note:** This is the current observed behavior of KRA's sandbox expecting JSON for initialization and sales.

*   **VSCU JAR Integration (Future Compatibility / Doc 4-aligned):**
    *   **Scenario:** If KRA mandates using their Virtual Sales Control Unit (VSCU) JAR file locally.
    *   **Communication:** Our Gateway sends **XML payloads** to `KRA_VSCU_JAR_BASE_URL` (e.g., `http://127.0.0.1:8088`), which is your locally running VSCU JAR. The JAR then communicates with KRA's backend.
    *   **Gateway Configuration:** `KRA_SIMULATION_MODE=false` and `KraDevice.device_type='VSCU'`.
    *   **Note:** The VSCU JAR download is currently a blocking point. The codebase is designed for Doc 4's **nested XML structures** for this mode.

*   **Simulation Mode (For Development):**
    *   **Scenario:** When you don't have access to KRA's live sandbox or VSCU JAR, or for rapid local development/testing.
    *   **Communication:** `KraApi::sendCommand` and `sendJsonCommand` are intercepted and routed to `MockServerController`.
    *   **Gateway Configuration:** `KRA_SIMULATION_MODE=true`. This allows you to test all functionalities locally with predefined mock responses (JSON for OSCU, XML for VSCU).

## 10. Testing

### 10.1. Unit & Feature Tests

*   **Run all tests:**
    ```bash
    php artisan test
    ```
*   **Specific tests:**
    ```bash
    php artisan test --filter KraApiTest
    php artisan test --filter KraDeviceInitializationTest
    ```

### 10.2. Live Sandbox Testing

Once KRA provides the necessary access (VSCU JAR or direct OSCU access confirmation), you'll switch `KRA_SIMULATION_MODE=false` and perform rigorous testing against the live environment using your Postman Collection. Be prepared for real KRA errors (time-outs, data format strictness, authentication failures).

## 11. Web UI (Client Portal)

The Gateway includes a basic web-based UI for client/taxpayer interaction:

*   **Authentication:** Laravel Breeze provides user authentication (admin@vscu.com, password: `password`).
*   **Dashboard:** Simple overview.
*   **KRA Reports:** Ability to select Taxpayer PINs and KRA Devices, then generate and view X Daily, Z Daily, and PLU reports.
*   **URL:** Access via `http://icore-tax-gateway.test/login` (or `/register`). Reports are at `http://icore-tax-gateway.test/reports`.

## 12. Troubleshooting Common Issues

*   **`502 Bad Gateway`:** PHP-FPM crash. Check `C:\laragon\tmp\php_error.log`. Ensure `php.ini` has `display_errors = On`.
*   **`404 Not Found` from KRA:** Endpoint path is wrong on KRA's side. Contact KRA support.
*   **`415 Unsupported Media Type` from KRA:** KRA's API expects JSON when you send XML (or vice-versa). Adjust `KraApi` method (`sendJsonCommand` vs `sendXmlCommand`).
*   **`cURL error 28: Operation timed out`:** Network connectivity issue (firewall, proxy, KRA server unresponsive). Use `ping` and `curl` from CMD to diagnose network reachability.
*   **`403 Forbidden`:** Authorization failure in a Form Request's `authorize()` method. Check `storage/logs/laravel.log` for specific `Log::warning` messages from `authorize()`. Verify `gatewayDeviceId`, `taxpayerPin`, and `ApiClient.allowed_taxpayer_pins` correctness.
*   **`Undefined array key "..."`:** Payload data mismatch with validation rules or service logic. Check Postman payload (typos, casing, missing fields, incorrect variable interpolation). Use `dd($request->all());` or `dd($this->input('field'));` to inspect.
*   **Laravel default page (instead of JSON):**
    *   If `authorize()` passes: Issue with `rules()` method (e.g., `date_format` issues) or a low-level crash during Form Request validation. Use `dd()` inside `rules()` to pinpoint.
    *   Ensure all caches are cleared (`php artisan route:clear`, `cache:clear`, `config:clear`) and Laragon services are restarted after ANY code change.

## 13. Future Enhancements

*   Refine API documentation (OpenAPI/Swagger).
*   Implement more robust logging and APM integration.
*   Automated reconciliation tools.
*   Comprehensive UI for all Gateway features.

## 14. License
