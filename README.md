# AdultPixScan - 图片内容安全检测工具

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Stars](https://img.shields.io/github/stars/RusianHu/AdultPixScan_Web.svg?style=social)](https://github.com/RusianHu/AdultPixScan_Web/stargazers)
[![Forks](https://img.shields.io/github/forks/RusianHu/AdultPixScan_Web.svg?style=social)](https://github.com/RusianHu/AdultPixScan_Web/network/members)
[![Issues](https://img.shields.io/github/issues/RusianHu/AdultPixScan_Web.svg)](https://github.com/RusianHu/AdultPixScan_Web/issues)
[![Last Commit](https://img.shields.io/github/last-commit/RusianHu/AdultPixScan_Web.svg)](https://github.com/RusianHu/AdultPixScan_Web/commits/main)
[![Repo Size](https://img.shields.io/github/repo-size/RusianHu/AdultPixScan_Web.svg)](https://github.com/RusianHu/AdultPixScan_Web)
[![Visitors](https://visitor-badge.laobi.icu/badge?page_id=RusianHu.AdultPixScan_Web)](https://github.com/RusianHu/AdultPixScan_Web)

[![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4.svg?logo=php&logoColor=white)](https://php.net/)
[![HTML5](https://img.shields.io/badge/HTML5-E34F26.svg?logo=html5&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/HTML)
[![CSS3](https://img.shields.io/badge/CSS3-1572B6.svg?logo=css3&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/CSS)
[![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E.svg?logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
[![Nginx](https://img.shields.io/badge/Nginx-009639.svg?logo=nginx&logoColor=white)](https://nginx.org/)
[![OpenRouter](https://img.shields.io/badge/OpenRouter-API-00BFFF.svg)](https://openrouter.ai/)
[![Gemini](https://img.shields.io/badge/Google_Gemini-2.5-4285F4.svg?logo=google&logoColor=white)](https://deepmind.google/technologies/gemini/)

AdultPixScan 是一个基于 AI 的图片内容安全检测工具，可以帮助用户快速识别图片中可能存在的不适宜内容。该工具使用 OpenRouter API 接入先进的 AI 模型，提供高精度的内容安全分析。

![image](https://github.com/user-attachments/assets/d7482fe4-9106-4e40-bf2b-43fe1c343c59)


![image](https://github.com/user-attachments/assets/08078eac-820a-489d-a29f-09f2345ece96)


## 功能特点

- 🖼️ 简单易用的图片上传界面
- 🤖 基于 Google Gemini 2.5 Flash 模型的内容分析
- 🔍 精确的成人内容检测与风险评估
- 📊 直观的风险概率显示（使用不同颜色标识风险等级）
- 📝 详细的 AI 判断依据说明
- 📱 响应式设计，支持各种设备访问
- 🔄 备用 API 端点自动切换，提高服务可靠性
- 💾 可选的图片备份功能，方便后续审核

## 技术栈

- 前端：HTML5, CSS3, JavaScript (原生)
- 后端：PHP
- AI 服务：OpenRouter API (支持多种 AI 模型)
- 服务器环境：Nginx + PHP

## 快速开始

### 系统要求

- PHP 7.4 或更高版本
- Web 服务器 (推荐 Nginx 或 Apache)
- 有效的 OpenRouter API 密钥

### 安装步骤

1. 克隆或下载本项目到您的 Web 服务器目录
2. 配置 `config.ini` 文件，设置您的 OpenRouter API 密钥和其他选项
3. 确保 Web 服务器配置正确，防止直接访问 `.ini` 文件
4. 访问网站开始使用

### 配置说明

编辑 `config.ini` 文件以自定义以下设置：

```ini
; API 密钥配置
api_key = "您的OpenRouter API密钥"

; 代理配置
use_proxy = false
proxy_url = "http://127.0.0.1:10809"

; IPv4/IPv6 配置
force_ipv4 = true

; 模型配置
model_name = "google/gemini-2.5-flash-preview"

; 超时配置（秒）
timeout = 180
connect_timeout = 30

; API 端点配置
api_endpoint = "https://openrouter.ai/api/v1/chat/completions"
api_endpoint_backup = "https://api.openrouter.ai/api/v1/chat/completions"

; 图片备份配置
enable_image_backup = true
image_backup_dir = "image_backups"
```

### Web 服务器配置

为了保护您的 API 密钥，请确保配置 Web 服务器以阻止直接访问 `.ini` 文件。

Nginx 示例配置：
```nginx
location ~ /\.ini$ {
   deny all;
}
```

Apache 示例配置 (.htaccess)：
```apache
<Files "*.ini">
  Order allow,deny
  Deny from all
</Files>
```

## 使用方法

1. 点击"选择图片"按钮上传需要分析的图片
2. 在预览区域查看上传的图片
3. 点击"开始分析"按钮
4. 等待 AI 分析完成（通常需要 5-15 秒）
5. 查看分析结果，包括风险概率、AI 判断依据和内容安全评估

## 错误处理

AdultPixScan 包含全面的错误处理机制：

- 客户端文件类型和大小验证
- 服务器端文件验证
- API 连接错误处理和自动切换备用端点
- 详细的错误日志记录
- 用户友好的错误提示

## 项目结构

```
AdultPixScan_Web/
├── index.html        # 主页面
├── style.css         # 样式表
├── script.js         # 前端 JavaScript
├── scan.php          # 后端处理脚本
├── config.ini        # 配置文件
├── LICENSE           # MIT 许可证
└── README.md         # 本文档
```

## 安全注意事项

- 请确保 `config.ini` 文件不能被公开访问
- 定期更新您的 API 密钥
- 如启用图片备份功能，请确保备份目录的安全性
- 考虑添加用户认证以限制访问

## 许可证

本项目采用 MIT 许可证 - 详情请查看 [LICENSE](LICENSE) 文件

## 作者

[RusianHu](https://github.com/RusianHu)

## 部署

项目部署在 https://yanshanlaosiji.top/AdultPixScan_Web/

---

**免责声明**：本工具仅用于内容安全检测和教育目的。AI 分析结果仅供参考，不应被视为最终判断。用户应对上传和处理的内容负责。
