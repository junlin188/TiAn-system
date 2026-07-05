# AGENTS.md

本文件给后续在本仓库中工作的 Codex/AI 代理使用。目标是快速恢复项目上下文、知道从哪里读代码、怎样本地运行和验证，以及哪些文件/数据不要误伤。

## 项目概览

- 项目名称：星闪提案系统 。
- 技术栈：原生 PHP 8.1+、SQLite、本地文件存储、少量原生 JavaScript/CSS。
- 应用形态：单入口 Web 应用，生产环境通过 Nginx + PHP-FPM 部署。
- 主要功能：
  - 登录、注册申请、忘记密码、图形验证码。
  - 用户/角色管理：`super_admin`、`admin`、`chief`、`member`。
  - 工作组、会员单位管理。
  - 提案任务管理。
  - 文件夹树、文件上传、预览、下载、重命名、复制、移动、删除。
  - 首席会员可在自己负责且未过期的提案下上传、删除、重命名文件。
- 存储：
  - SQLite 数据库：`storage/app.sqlite`。
  - 上传文件：`storage/uploads/`。
  - 验证码临时文件/相关数据：`storage/captcha/`。

## 目录地图

- `public/index.php`
  - 应用核心入口。数据库初始化、动作路由、权限判断、业务处理、页面渲染基本都在这个文件里。
  - 重要函数区域：
    - `init_db()`：建表和种子数据。
    - `handle_actions()`：按 `?action=...` 分发请求。
    - `current_user()` / `require_login()` / `is_admin()` / `is_chief()`：会话与权限。
    - `upload_file_to_dir()` / `file_response()`：文件上传和预览/下载。
    - `render_*()`：各页面和弹窗渲染。
- `public/assets/style.css`
  - 全站样式。
- `public/assets/app.js`
  - 弹窗、表单填充、文件夹树展开折叠、本地状态等前端交互。
- `storage/`
  - 运行时持久数据目录。不要随意删除或覆盖真实环境中的数据库和上传文件。
- `deploy/`
  - 服务器部署、审计、初始化和冒烟测试脚本。
- `.github/workflows/deploy.yml`
  - GitHub Actions：推送到 `main` 后先 PHP lint，再 rsync 到阿里云 ECS。
- `.deploy-secrets/`
  - 本地部署密钥/秘密目录，被 `.gitignore` 忽略。不要提交。

## 本地运行

需要 PHP 8.1+，建议启用扩展：

- `pdo_sqlite`
- `fileinfo`
- `gd`
- `mbstring`

启动开发服务器：

```bash
php -S 127.0.0.1:8001 -t public
```

访问：

```text
http://127.0.0.1:8001
```

首次访问会自动创建 SQLite 数据库和示例数据。

默认账号：

| 角色 | 账号 | 密码 |
| --- | --- | --- |
| 超管/管理员 | `admin` 或 `admin@example.com` | `admin123456` |
| 首席会员 | `shouxi2` 或 `234567@qq.com` | `chief123456` |
| 普通会员 | `member1` 或 `member@test.com` | `member123456` |

## 验证命令

PHP 语法检查：

```bash
php -l public/index.php
```

如果在 Linux/CI 环境，可检查所有 PHP 文件：

```bash
find public -name '*.php' -print0 | xargs -0 -n1 php -l
```

生产/服务器侧登录冒烟测试：

```bash
BASE_URL=http://127.0.0.1/ bash deploy/smoke-test-login.sh
```

服务器只读审计：

```bash
bash deploy/audit-server.sh
```

初始化数据库脚本：

```bash
php deploy/init-db.php
```

## 部署说明

生产部署参考 `deploy/README.md`。

服务器初始化脚本：

```bash
APP_DIR=/var/www/tian-system \
DEPLOY_USER=deploy \
DOMAIN=_ \
DEPLOY_PUBLIC_KEY='ssh-ed25519 ... github-actions-deploy-key' \
bash deploy/setup-server.sh
```

GitHub Actions 需要配置这些 Secrets：

- `ALIYUN_HOST`
- `ALIYUN_PORT`
- `ALIYUN_USER`
- `ALIYUN_SSH_KEY`
- `ALIYUN_TARGET_DIR`
- `PUBLIC_URL`

部署工作流会刻意排除并保留：

- `storage/app.sqlite`
- `storage/uploads/***`
- `storage/captcha/***`

重大维护前先备份 `storage/app.sqlite` 和 `storage/uploads/`。

## 开发约定

- 这个项目当前不是框架项目，没有 Composer、npm、构建步骤或测试框架。
- 优先保持“原生 PHP 单文件应用”的既有风格，除非用户明确要求重构。
- 修改业务逻辑前先读 `public/index.php` 中相邻函数，避免破坏已有动作分发和权限假设。
- 新增 `action` 时，通常要同步处理：
  - `handle_actions()` 中的分发。
  - 对应权限检查，如 `require_admin()`、`is_chief()`。
  - 成功/失败后的 `flash()` 与 `redirect()`。
  - 前端表单 `action="?action=..."`。
- 涉及文件系统时，注意数据库记录和真实文件要一起维护。
- 涉及目录权限时，关注：
  - `directory_permissions`
  - `allowed_dir_ids()`
  - `can_view_dir()`
  - `grant_dir()` / `grant_all_dirs()`
- 管理员角色：
  - `super_admin` 和 `admin` 都被 `is_admin()` 视为管理员。
  - `super_admin` 是特殊保护账号，默认绑定“星闪联盟”会员单位。
- 普通会员只能访问被授权目录。
- 首席会员的提案文件操作受提案负责人、上传者和有效期限制。
- 中文内容请保持 UTF-8。Windows PowerShell 有时会把 UTF-8 中文显示成乱码，判断前优先用 `rg` 或编辑器查看。

## 数据库表

主要表：

- `workgroups`：工作组。
- `member_units`：会员单位。
- `users`：用户、角色、状态、所属工作组/会员单位。
- `directories`：文件夹树，含 `parent_id` 和 `path`。
- `files`：上传文件元数据，实际文件在 `storage/uploads/`。
- `proposals`：提案任务。
- `directory_permissions`：用户可访问目录。
- `proposal_uploads`：提案与上传文件的关联。

数据库 schema 在 `public/index.php` 的 `init_db()` 中定义。

## 当前仓库注意事项

- `.gitignore` 已忽略运行时数据库、上传文件、验证码文件和 `.deploy-secrets/`。
- 不要提交真实密钥、服务器私钥、生产数据库或用户上传文件。
- 工作区可能存在用户未提交的改动。开始编辑前先看 `git status --short`，不要回滚不是自己做的改动。
- 根目录曾出现未跟踪中文文件名，处理前先确认用途，不要贸然删除。

## 推荐接手流程

1. 运行 `git status --short`，识别用户已有改动。
2. 阅读 `README.md`、`deploy/README.md` 和本文件。
3. 用 `rg -n "function 名称|action 名称|页面文案" public/index.php public/assets/app.js public/assets/style.css` 定位相关代码。
4. 小改动优先保持局部修改；涉及权限、文件、数据迁移时扩大检查范围。
5. 至少运行 `php -l public/index.php`。
6. 如果改了登录、验证码、提案或文件流程，启动本地服务手动走一遍关键角色路径。
