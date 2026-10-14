# AIAbstract · WordPress AI 摘要插件

> 在文章正文上方自动生成简洁的 AI 摘要，一篇文章缓存 3 条不同角度，每次访问随机呈现。
> 兼容 DeepSeek / OpenAI / Kimi / 通义千问 / 智谱 GLM / 讯飞星火 / 百度千帆 / 腾讯混元 / 豆包 / 硅基流动 / OpenRouter / Groq 等一切 OpenAI 协议接口。

![Version](https://img.shields.io/badge/version-1.8.0-4f7cff) ![License](https://img.shields.io/badge/license-GPL--2.0-green) ![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue) ![PHP](https://img.shields.io/badge/PHP-7.4%2B-8892BF)

**仓库托管**：[Gitee（主仓库）](https://gitee.com/onewumi/wordpress-aiabstract) · [GitHub（镜像）](https://github.com/onewumi/wordpress-aiabstract)

---

## ✨ 特性

- **多角度摘要，每次访问都新鲜** — 一次请求让 AI 生成 3 条不同侧重点的摘要（核心观点 / 关键信息 / 延伸启发），缓存后每次刷新随机展示一条（v1.6.0）
- **15 家服务商一键接入** — 设置页下拉选择 DeepSeek、OpenAI、Kimi、通义千问、智谱 GLM、讯飞星火、百度千帆、腾讯混元、豆包火山方舟、硅基流动、OpenRouter、Groq、OpenCode Zen/Go 等，自动填入 API 地址与模型建议；也支持任意自定义 OpenAI 兼容地址（v1.8.0）
- **API 地址自动归一化** — 填 base 地址（如 `https://opencode.ai/zen/go/v1`）也能用，保存与请求时自动补全 `/chat/completions`（v1.8.0）
- **连接检测 + 模型下拉** — 「测试连接」一键验证 Key 与连通性，成功后自动从 `/models` 端点拉取模型 ID 填充下拉框；不支持该端点的服务可手动输入（v1.7.1）
- **四套前端模板** — 现代卡片（默认）/ 纸墨古风 / 流光玻璃 / 霓虹光晕，样式统一收敛于 `frontend.css`，明暗主题均可读；默认模板支持自定义 HTML 结构（v1.7.0）
- **打字机效果** — 摘要逐字打出，自动兼容自定义模板（基于 `data-aiabstract-abstract` 标记定位），对不兼容的旧浏览器自动降级直显
- **缓存与降级** — 摘要缓存于本地 JSON（原子写入），超过 3 天仍未生成成功的文章自动标记阻止，避免反复浪费 API 配额
- **卡片式管理后台** — 设置页分区卡片、模板可视化选择、字数滑块、字符统计；管理页渐变统计卡、状态徽章、摘要管理（隐藏 / 重新生成 / 导出）
- **隐私优先** — 无任何统计上报、无外部遥测；日志仅调试模式写入且仅管理员可见，缓存目录有访问保护

## 🖼️ 模板效果

打开 [`demo/preview.html`](demo/preview.html) 可在浏览器直接预览四套模板效果（无需安装 WordPress）。

## 📥 安装

**方法一：WordPress 后台安装**
1. 在 Releases 页面下载 `AIAbstract_v1.8.0.zip`（[Gitee Releases](https://gitee.com/onewumi/wordpress-aiabstract/releases) / [GitHub Releases](https://github.com/onewumi/wordpress-aiabstract/releases)）
2. 后台 → 插件 → 安装插件 → 上传 ZIP → 启用

**方法二：git clone**
```bash
# 克隆到 wp-content/plugins/ 下，目录名保持 aiabstract 或 AIAbstract 均可
cd wp-content/plugins/

# Gitee（国内访问快）
git clone https://gitee.com/onewumi/wordpress-aiabstract.git aiabstract
# 或 GitHub（海外镜像）
git clone https://github.com/onewumi/wordpress-aiabstract.git aiabstract

# 也可用 SSH 方式
git clone git@gitee.com:onewumi/wordpress-aiabstract.git aiabstract
git clone git@github.com:onewumi/wordpress-aiabstract.git aiabstract
```

**环境要求**
- WordPress 5.8+，PHP 7.4+（需 `mbstring`、`json` 扩展）
- 一个 OpenAI 兼容的 API 服务商账号（DeepSeek / OpenRouter / 任意自建代理…）

## ⚙️ 快速配置

1. 后台 → **设置 → AI摘要**
2. 「服务提供方」下拉选择你的服务商（或选「自定义」手动填写）
3. 填写 **API Key**（DeepSeek 等平台的密钥）
4. 点击 **🔍 测试连接**，成功后会**自动拉取模型列表**；从下拉选择模型或手动输入（如 `deepseek-chat`）
5. 「摘要字数上限」建议 100 字左右；每篇文章会生成 3 条，可选中意的显示模板
6. 保存设置 → 到任意文章页查看效果

> 已有摘要的文章默认沿用缓存；需要重新生成 3 条新摘要时，到 **工具 → AI摘要管理** 点「重新生成」。

## 🧩 工作原理

```
文章页 the_content
   └─ 读取 abstracts.json 缓存（按文章 ID）
        ├─ 命中 → 随机抽取其中 1 条 → 套模板渲染（含打字机）
        └─ 未命中 → 调用 API（system 指令要求返回 3 条，--- 分隔）
                    └─ 解析为数组 → 原子写入缓存 → 随机展示 1 条
```

- 摘要请求：`POST {base}/chat/completions`，OpenAI 标准格式，`max_tokens` 随字数上限自动放大
- 模型列表：`GET {base}/models`（自动推导），兼容 `data[].id` 与 `models[]` 两种响应
- 缓存文件：`cache/abstracts.json`（每条含 3 条摘要文本 + 状态 + 时间戳）

## 📁 目录结构

```
├── README.md                           # 项目说明（本文档）：简介、安装、配置、FAQ
├── CHANGELOG.md                        # 完整更新日志（按版本）
├── LICENSE                             # GPL-2.0 开源协议全文
├── .gitignore                          # Git 忽略规则（运行时缓存 / 构建产物 / IDE）
├── ai-abstract.php                     # 插件主入口：定义常量与自动加载、激活/停用钩子、插件默认选项
├── uninstall.php                       # 卸载脚本：删除选项、定时任务与缓存文件
│
├── admin/
│   └── manage-abstracts.php            # 「工具 → AI摘要管理」页面：摘要列表、隐藏/显示、重新生成、
│                                       #   删除/导出、清理缓存；API 测试与清缓存 AJAX 处理
│
├── assets/
│   ├── css/
│   │   ├── admin.css                   # 后台样式：设置页卡片式布局、管理页统计卡/徽章/模态框
│   │   └── frontend.css                # 前端样式：现代卡片/纸墨古风/流光玻璃/霓虹光晕 四套模板
│   └── js/
│       ├── admin.js                    # 后台交互：Key 显隐、字数滑块联动、字符统计、模板卡片选中、
│       │                               #   测试连接与拉取模型 AJAX 调用
│       ├── frontend-enhanced.js        # 前端打字机效果：按 data-aiabstract-abstract 定位摘要，
│       │                               #   思考中动画 → 逐字打出，含 5s/10s 兜底直显
│       └── frontend-simple.js          # 禁用打字机时的极简脚本：随机标语 + 直接显示摘要
│
├── includes/
│   ├── class-aiabstract-core.php       # 核心逻辑：the_content 注入摘要、3 条摘要随机抽取、JSON 缓存
│   │                                   #   读写（原子写入）、模板渲染、调试面板、日志（仅调试写入）
│   ├── class-aiabstract-admin.php      # 后台设置页：卡片式表单渲染、15 家服务商预设、设置校验、
│   │                                   #   拉取模型列表 AJAX
│   └── class-aiabstract-api.php        # API 层：OpenAI 兼容对话请求、地址归一化、连接测试、
│                                       #   模型列表接口、批量生成
│
├── templates/
│   ├── template1.php                   # 模板① 纸墨古风：朱印标签 + 宋体 + 米纸质感（含打字机结构）
│   ├── template2.php                   # 模板② 流光玻璃：动态渐变背景 + 毛玻璃卡片
│   └── template3.php                   # 模板③ 霓虹光晕：跑灯渐变边框 + 暗色霓虹字
│
├── cache/
│   ├── .htaccess                       # 访问保护（Apache）：禁止 Web 直接访问本目录
│   ├── index.php                       # 访问保护：防止目录列表（勿删除）
│   └── （运行时生成）                   # abstracts.json 摘要缓存 / plugin.log 调试日志
│                                       #   —— 不入库，清除后插件会自动重建
│
├── languages/
│   └── aiabstract.pot                  # 翻译模板（gettext / Poedit）
│
└── demo/
    └── preview.html                    # 四套模板静态预览页：克隆后浏览器直接打开即可查看效果
```

## ❓ 常见问题

| 问题 | 说明 |
| --- | --- |
| 摘要不显示 / 一直「思考中」 | 开启「调试模式」后访问文章页，看右下角日志定位；确认 Key 有效且模型名正确 |
| 提示「不支持 /models 端点」 | 该服务未提供模型列表接口，忽略即可，直接手动输入模型名 |
| 摘要被截断 | 「摘要字数上限」与 `max_tokens` 已联动，若仍截断说明该模型输出上限较低，可调低字数上限 |
| 刷新后摘要不变 | 若使用了整页 HTML 缓存（CDN / 缓存插件），请排除文章页或按天轮换展示 |
| 旧版（iLogs）用户升级 | v1.5.0 起已移除 iLogs 共享接口，仅保留自配 API；升级后需在设置页重新配置 |
| 模板在暗色主题下不协调 | 前端模板均为亮卡片设计，明暗主题均可读；霓虹版为暗底，介意可选「现代卡片」 |

## 📜 更新日志

- **v1.8.0** 主流服务商一键接入（15 家预设）、API 地址自动归一化（base → /chat/completions）
- **v1.7.2** 安全审计：调试面板仅管理员可见、缓存目录访问保护、日志仅调试模式写入
- **v1.7.1** 「测试连接」与「拉取模型列表」
- **v1.7.0** 后台与前端 UI 全面重塑（卡片式设置页、四套新模板）
- **v1.6.0** 每篇缓存 3 条不同角度摘要，访问随机展示
- **v1.5.0** 移除 iLogs 共享接口与统计上报，仅保留自定义 API

## 🔒 安全说明

- API Key 仅存于站点 `wp_options`，日志不记录 Key；请求全部经服务端代理，浏览器不接触密钥
- 全部 AJAX 端点均校验 nonce 与 `manage_options` 权限
- 调试日志（含文章内容）仅在开启调试模式时写入，且调试面板仅管理员可见
- `cache/` 目录带有 `.htaccess` / `index.php` 双层访问保护

## 📄 开源协议

本项目基于 [GPL-2.0](LICENSE) 开源，欢迎 Fork、提交 Issue 与 PR。

**致谢**：摘要生成思路启发自 [iLogs.cn](https://iLogs.cn) 的共享摘要服务；本插件为独立的 WordPress 实现，不包含任何原作者代码与遥测。

**免责声明**：本插件提供的 AI 摘要服务依赖于第三方 API，插件作者不对摘要内容的准确性与质量承担法律责任，请自行审查生成内容。各服务商接入地址如有变动，以官方文档为准。