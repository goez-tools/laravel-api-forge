# Laravel API Forge

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue.svg)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/laravel-%3E%3D11.0-red.svg)](https://laravel.com/)
[![Laravel Installer](https://img.shields.io/badge/laravel--installer-%3E%3D5.0-orange.svg)](https://laravel.com/docs/installation)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

*正體中文 | [English](README.md)*

Laravel API Forge 是一個強大的命令列工具，幫助您快速搭建完整的 Laravel API 專案，並預先配置現代化的開發實踐和必要的套件。它基於 Laravel Zero 構建，自動化設置生產就緒的 Laravel API，並提供可選功能如 Redis 快取、RBAC（角色權限控制）和模組化架構。

## ✨ 功能特色

- **🚀 快速設置**：幾分鐘內創建完整的 Laravel API 專案
- **🔧 環境驗證**：自動檢查必要工具和版本
- **📝 程式碼品質**：使用 Laravel Pint 自動格式化程式碼
- **🔄 Git 整合**：分步驟 Git 提交，並附有意義的訊息
- **🐳 Docker 就緒**：預配置 Laravel Sail 與 MySQL 和 Redis
- **🔐 身份驗證**：Laravel Sanctum API 身份驗證設置
- **📊 資料庫**：使用 Laravel Sail 的 MySQL 資料庫配置
- **⚡ Redis 快取**：可選的 Redis 快取配置
- **🛡️ RBAC 系統**：可選的角色權限控制系統（Spatie Permission）
- **🔐 一次性密碼**：可選的 OTP 套件以增強安全性
- **🧩 模組化架構**：可選的 Laravel Modules 模組化開發
- **📚 API 文件**：Spectator 用於 OpenAPI 規範測試
- **🎯 資料傳輸物件**：Laravel Data 用於結構化資料處理
- **🪝 Git Hooks**：預配置的 Git hooks 用於程式碼品質檢查

## 📋 系統需求

在使用 Laravel API Forge 之前，請確保您已安裝以下工具：

- **PHP 8.2+**：[下載 PHP](https://www.php.net/downloads.php)
- **Composer**：[安裝 Composer](https://getcomposer.org/download/)
- **Laravel Installer 5.0+**：`composer global require laravel/installer`
- **Git**：[安裝 Git](https://git-scm.com/downloads)

該工具會在開始前自動驗證這些需求。

## 🚀 安裝方式

使用 Composer 全域安裝 Laravel API Forge：

```bash
composer global require goez/laravel-api-forge
```

確保您的全域 Composer vendor bin 目錄在 `$PATH` 中。您可以將此行加入您的 shell 配置檔（`.bashrc`、`.zshrc` 等）：

```bash
# 針對 bash/zsh
export PATH="$PATH:$HOME/.composer/vendor/bin"

# 某些系統的替代路徑
export PATH="$PATH:$HOME/.config/composer/vendor/bin"
```

安裝完成後，您可以全域使用此工具：

```bash
laravel-api-forge --version
laravel-api-forge list
```

## 📖 使用方式

### 基本用法

創建一個新的 Laravel API 專案：

```bash
laravel-api-forge new my-api-project
```

### 進階用法（帶選項）

```bash
laravel-api-forge new my-api-project --redis --rbac --otp --modules --test-sqlite
```

### 可用選項

- `--redis`：使用 Redis 作為快取儲存
- `--rbac`：安裝和配置 RBAC（角色權限控制）套件
- `--otp`：安裝和配置一次性密碼套件
- `--modules`：安裝和配置 Laravel Modules 模組化架構
- `--test-sqlite`：使用 SQLite 作為測試資料庫（在 Sail 設置後回復到 SQLite）

### 互動式模式

如果您沒有指定選項，工具會以互動方式詢問您：

```bash
laravel-api-forge new my-api-project

# 系統會詢問：
# Do you want to use Redis as cache store? (yes/no)
# Do you want to install RBAC package? (yes/no)
# Do you want to install One-Time-Passwords package? (yes/no)  
# Do you want to install modular architecture? (yes/no)
# Do you want to use SQLite for testing database? (yes/no)
```

### 自動更新

使用內建的自動更新功能保持您的 Laravel API Forge 為最新版本：

```bash
# 檢查更新
laravel-api-forge self-update --check

# 更新到最新版本
laravel-api-forge self-update

# 回滾到前一版本（如果需要的話）
laravel-api-forge self-update --rollback

# 強制更新，即使版本相同
laravel-api-forge self-update --force

# 包含預發布版本
laravel-api-forge self-update --pre-release
```

**注意**：自動更新功能僅在使用 PHAR 建置版本的工具時有效。

## 🏗️ 創建的專案內容

此工具創建一個完整的 Laravel API 專案，包含：

### 核心設置
- Laravel 11+ 與 Pest 測試框架
- Laravel Sanctum API 身份驗證
- API 路由使用 `/v1` 前綴
- MySQL 資料庫配置
- Laravel Sail Docker 開發環境

### 可選功能（根據您的選擇）

#### Redis 快取（`--redis`）
- Redis 快取配置
- 移除資料庫快取遷移
- 更新環境檔案

#### RBAC 系統（`--rbac`）
- Spatie Laravel Permission 套件
- 支援團隊權限
- 預配置的 User 模型與角色
- Abilities 目錄結構

#### 一次性密碼（`--otp`）
- 一次性密碼套件整合
- 增強身份驗證安全性
- 支援時間型和計數器型 OTP
- 與現有身份驗證流程簡易整合

#### 模組化架構（`--modules`）
- Laravel Modules 套件
- 模組化目錄結構
- Vite 整合模組資源
- Composer merge plugin 處理模組依賴

### 額外套件
- **Laravel Data**：用於結構化資料傳輸物件
- **Spectator**：用於 OpenAPI 規範測試
- **Laravel Pint**：用於程式碼風格格式化

### 開發工具
- 預配置 Git hooks（pre-commit、pre-push、post-merge）
- 使用 Pint 自動格式化程式碼
- 環境檔案同步（.env 和 .env.example）
- 分步驟 Git 提交以獲得更好的歷史記錄
- 可選的 SQLite 測試資料庫配置

## 🔄 開發流程

創建專案後：

```bash
cd my-api-project

# 啟動開發環境
./vendor/bin/sail up -d

# 執行遷移
./vendor/bin/sail artisan migrate

# 執行測試
./vendor/bin/sail composer test

# 程式碼格式化
./vendor/bin/sail composer lint
```

## 📁 專案結構

```
my-api-project/
├── app/
│   ├── Models/User.php          # 增強了 Sanctum 和 RBAC 功能
│   └── Abilities/               # RBAC 能力（如果啟用）
├── docs/v1/                     # API 文件
├── modules/                     # 模組化架構（如果啟用）
├── tests/
├── .git-hooks/                  # 預配置的 Git hooks
├── docker-compose.yml           # Laravel Sail 配置
└── vite-module-loader.js        # 模組資源載入（如果啟用）
```

## ⚙️ 配置說明

工具會自動配置：

- **資料庫**：使用 Laravel Sail 的 MySQL
- **快取**：Redis（如果選擇）或檔案快取
- **API**：使用 `/v1` 前綴的 Sanctum 身份驗證
- **測試**：使用 LazilyRefreshDatabase 的 Pest
- **程式碼品質**：帶有 pre-commit hooks 的 Laravel Pint

## 🤝 貢獻

歡迎貢獻！請隨時提交 Pull Request。

1. Fork 這個專案
2. 創建您的功能分支（`git checkout -b feature/amazing-feature`）
3. 提交您的變更（`git commit -m 'Add some amazing feature'`）
4. 推送到分支（`git push origin feature/amazing-feature`）
5. 開啟一個 Pull Request

## 📝 授權

Laravel API Forge 是在 [MIT 授權](LICENSE) 下的開源軟體。

## 🙏 致謝

- 使用 [Laravel Zero](https://laravel-zero.com/) 構建
- 受到現代 Laravel 開發實踐的啟發
- 感謝 Laravel 社群提供的優秀生態系統

---

**祝您編程愉快！🚀**
