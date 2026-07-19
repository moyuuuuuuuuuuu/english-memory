# English Memory 项目总计划与换机交接

> 最后更新：2026-07-19
> 项目目录：`/mnt/e/dnmp/www/english-memory`（Windows：`E:\dnmp\www\english-memory`）
> 本文件是项目进度、执行顺序和换机恢复的唯一入口。架构细则以 `AGENTS.md` 为准。

## 1. 产品目标

面向中文用户的 Android/iOS 英语记忆应用。用户可以输入单词或句子，也可以拍照、裁剪并通过本地 OCR 得到可手动修改的英文内容。后端调用扣子工作流生成：

- 中文强关联记忆钩子或语义场景；
- 音标、词性、释义、例句和故事；
- 记忆插画；
- 可保存、编辑、复习并跨设备同步的记忆卡。

移动端采用 Flutter，后端采用 Webman + MySQL + Redis。视觉方向为暖白香槟色、柔和渐变、精致故事书插画和克制的粒子反馈。

## 2. 固定技术决策

- 后端：PHP 8.2、Webman 2.2、Workerman 5、PHPUnit 11。
- 数据：MySQL 8 持久化；Redis 8 用于会话、限流、队列和短期缓存。
- 移动端：Flutter，同时维护 Android 与 iOS 工程。
- OCR：移动端本地完成；拍照裁剪后识别，提交前允许手动修改。
- AI：扣子中国工作流 `7663771858193448987`，凭证只保存在后端 `.env`。
- 域名：开发环境通过 Nginx 使用 `http://e.test`，反代 `php82:8787`。
- API：成功为 `{"success":true,"data":{}}`；失败为 `{"success":false,"error":{"code":"...","message":"..."}}`。
- 数据库变更：只允许新增 `database/migrations/*.sql` 迁移文件；已执行迁移不可修改。
- 仓库根目录就是 Webman 后端；Flutter 客户端放在 `mobile/`，不增加 `server/` 包装层。

## 3. 代码架构

```text
Route -> Controller -> Business -> Service / Model
                              -> Entity
                              -> Common
```

- `app/controllers/`：只处理 HTTP 输入输出，并调用 Business。
- `app/businesses/`：校验、权限、事务和业务编排。
- `app/services/`：扣子、存储、邮件、OCR 等第三方或基础设施调用。
- `app/services/contracts/`：可替换服务接口。
- `app/entities/`：强类型领域/传输对象。
- `app/models/`：持久化模型和查询作用域。
- `app/common/{base,enums,traits,exceptions,helpers}/`：跨领域公共能力。
- `app/processes/`：队列消费者和常驻进程。

详细依赖规则、安全约束和测试要求见 `AGENTS.md`。

## 4. 当前已完成

### 工作流与同步生成接口

- [x] 扣子工作流已发布并能生成结构化记忆卡和图片 URL。
- [x] 已接入 `CozeWorkflowService`，并隔离提供方的嵌套 `card_json` 格式。
- [x] 已实现 `POST /api/memory-cards/generate` 同步开发接口。
- [x] 输入支持 `text`、`content_type` 和 `memory_style`。
- [x] Controller、Business、Service、Entity 已迁移到复数目录结构。

### 环境与持久化基础

- [x] `.env` 自动加载，`.env.example` 已提供且不包含真实密钥。
- [x] 安装 `webman/database`、`webman/redis` 和相关依赖。
- [x] MySQL 与 Redis 配置全部通过环境变量读取。
- [x] 创建数据库 `english_memory`。
- [x] 创建迁移执行器 `php scripts/migrate.php`，记录文件名与 SHA-256。
- [x] 完成并执行 `0001` 至 `0003` 三份迁移。
- [x] 已创建 `users`、`refresh_tokens`、`memory_cards`、`ai_generation_jobs`、`review_events`、`daily_learning_stats` 和 `migrations` 表。
- [x] 迁移可重复执行，已执行文件内容变化时会拒绝继续。
- [x] Redis `PING`、MySQL 查询、Nginx 配置和 `e.test` 路由均已验证。
- [x] 当前完整测试基线：17 tests，33 assertions。

### 账户、设备会话与鉴权

- [x] 支持邮箱或用户名注册，密码只保存安全哈希，并拒绝重复身份。
- [x] 支持邮箱或用户名登录，Access Token 以不透明随机值签发并存入 Redis。
- [x] 实现 Bearer 鉴权中间件和受保护的当前用户接口。
- [x] Refresh Token 只保存 SHA-256，携带设备名，支持 30 天过期、轮换和防重放。
- [x] 退出时撤销当前 Access Token 与对应 Refresh Token。
- [x] 新增 `password_reset_tokens` 迁移，实现中性找回响应、邮件服务边界、单次限时密码重置。
- [x] 当前完整测试基线：48 tests，165 assertions。

## 5. 后续执行路线

### 阶段 2：账户、设备会话与鉴权（已完成）

- [x] 为注册、登录、刷新、退出和受保护接口先写失败测试。
- [x] 实现 `User`、`RefreshToken` 模型及对应 Entity。
- [x] 实现用户名或邮箱注册、密码登录、短期 Access Token。
- [x] Refresh Token 只存哈希，携带设备信息，支持轮换与吊销。
- [x] 实现鉴权中间件、用户上下文和资源归属校验。
- [x] 设计找回密码 Token 与可替换邮件服务边界。
- [x] 输出接口：`/api/auth/register`、`login`、`refresh`、`logout`、`forgot-password`、`reset-password`。

### 阶段 3：异步 AI 记忆卡生成

- [ ] 新建记忆卡时立即返回 Card ID 与 Job ID。
- [ ] 使用 Redis 队列和 `app/processes/` 消费者调用扣子。
- [ ] 持久化 `queued`、`generating_text`、`generating_image`、`completed`、`failed` 状态。
- [ ] 增加请求幂等键、失败重试、用户限流与结果字段校验。
- [ ] 暂时保留同步接口作为仅开发可用开关，移动端迁移完成后删除。

### 阶段 4：应用自有图片存储

- [ ] 及时下载扣子临时图片，限制 HTTPS 主机、跳转、超时、MIME、尺寸和文件大小。
- [ ] 生成 1024px 与 512px 移动端版本并计算校验和。
- [ ] 数据库只保存应用自有 URL；删除卡片或账户时清理图片。
- [ ] 先实现本地存储合同，后续可替换对象存储。

### 阶段 5：卡片库与跨设备同步

- [ ] 卡片列表、详情、编辑、收藏、标签、重新生成和软删除。
- [ ] 游标分页、搜索与状态筛选。
- [ ] 增加单调递增版本号、删除墓碑和增量同步游标。
- [ ] 多设备冲突必须显式返回双方元数据，禁止静默覆盖。

### 阶段 6：间隔复习与游戏数据

- [ ] 实现今日复习、提交答案和统计总览接口。
- [ ] 支持图片回忆、听音拼写和中译英三种模式。
- [ ] 调度算法覆盖首次复习、答对、答错、逾期和时区边界。
- [ ] XP、连对、等级和连续学习天数只做反馈，不影响间隔算法。
- [ ] 答案提交使用幂等键，避免离线重传产生重复统计。

### 阶段 7：Flutter 基础与本地 OCR

- [ ] 初始化 Android/iOS Flutter 工程和暖白香槟设计系统。
- [ ] 实现 Home、Review、Capture、Library、Profile 五个主导航。
- [ ] 实现登录、刷新凭证、安全存储与退出。
- [ ] 实现拍照、裁剪、本地英文 OCR、可编辑确认和单词/句子选择。
- [ ] 实现本地缓存和可靠的待同步队列。

### 阶段 8：移动端卡片、复习与离线能力

- [ ] 展示生成进度、文字先行、图片后补、失败重试和编辑状态。
- [ ] 实现谐音故事与语义场景两套卡片布局。
- [ ] 实现系统发音、三种复习玩法、生命值、XP 和连续反馈。
- [ ] 支持弱动画模式，粒子仅用于成功反馈。
- [ ] 测试飞行模式、恢复联网、排队同步和冲突处理。

### 阶段 9：安全、部署与发布

- [ ] 鉴权与 AI 限流、请求 ID、结构化日志和敏感字段脱敏。
- [ ] 健康/就绪检查，不泄露凭证。
- [ ] Webman 作为容器服务自动启动，而不是依赖手工守护进程。
- [ ] 编写 MySQL 备份恢复、图片保留、API、部署和隐私删除文档。
- [ ] 增加后端与 Flutter CI。
- [ ] 构建并冒烟测试 Android APK；记录 macOS/Xcode 的 iOS 签名步骤。

## 6. 换设备恢复步骤

1. 从远程仓库克隆本项目；确认当前所有文件已经提交并推送。
2. 安装 Docker、Docker Compose、Git 和 Flutter；后端 PHP 不需要安装到宿主机。
3. 复制 `.env.example` 为 `.env`，填入 MySQL、Redis 和扣子凭证；不要提交 `.env`。
4. 确保 hosts 或 SwitchHosts 包含 `127.0.0.1 e.test`。
5. 启动 DNMP 的 `mysql`、`redis`、`php82`、`nginx` 容器，并保持项目挂载路径与新设备一致。
6. 在 PHP 容器内安装依赖并执行迁移：

```bash
docker exec -w /www/english-memory php82 composer install
docker exec -w /www/english-memory php82 php scripts/migrate.php
docker exec -w /www/english-memory php82 php start.php start -d
```

7. 执行基线检查：

```bash
docker exec -w /www/english-memory php82 vendor/bin/phpunit
docker exec -w /www/english-memory php82 composer validate --no-check-publish
docker exec nginx nginx -t
curl -X POST http://e.test/api/memory-cards/generate \
  -H "Content-Type: application/json" \
  -d '{"text":""}'
```

最后一个请求应返回 HTTP 422，错误码为 `INVALID_INPUT`。

## 7. 每次开发的固定流程

1. 阅读本文件和 `AGENTS.md`。
2. 从第一个未完成阶段继续，不跳过依赖阶段。
3. 行为变更先写失败测试，再写最小实现。
4. SQL 结构变更必须新增迁移，不修改旧迁移。
5. 执行针对性测试、完整测试、语法检查和 `git diff --check`。
6. 更新本文件中的完成状态和最新测试基线。
7. 提交并推送代码，确保另一台设备能够恢复。

## 8. 当前交接点

下一次开发从“阶段 3：异步 AI 记忆卡生成”开始。第一项工作是为创建卡片立即返回 Card ID/Job ID、请求幂等键、队列状态和用户归属编写失败测试，然后新增必要迁移并实现 Redis 队列消费者。

阶段 2 已合并到本地 `master`。推送远程仓库后再开始阶段 3，确保新设备可以从远程仓库恢复。
