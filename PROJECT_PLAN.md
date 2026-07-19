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
- [x] 完成并执行 `0001` 至 `0005` 五份迁移。
- [x] 已创建 `users`、`refresh_tokens`、`memory_cards`、`ai_generation_jobs`、`review_events`、`daily_learning_stats` 和 `migrations` 表。
- [x] 迁移可重复执行，已执行文件内容变化时会拒绝继续。
- [x] Redis `PING`、MySQL 查询、Nginx 配置和 `e.test` 路由均已验证。
- [x] `0005` 已扩展异步任务的幂等键、请求哈希、失败类型、父任务和派发时间。

### 账户、设备会话与鉴权

- [x] 支持邮箱或用户名注册，密码只保存安全哈希，并拒绝重复身份。
- [x] 支持邮箱或用户名登录，Access Token 以不透明随机值签发并存入 Redis。
- [x] 实现 Bearer 鉴权中间件和受保护的当前用户接口。
- [x] Refresh Token 只保存 SHA-256，携带设备名，支持 30 天过期、轮换和防重放。
- [x] 退出时撤销当前 Access Token 与对应 Refresh Token。
- [x] 新增 `password_reset_tokens` 迁移，实现中性找回响应、邮件服务边界、单次限时密码重置。
- [x] 当前完整测试基线：48 tests，165 assertions。

### 异步 AI 记忆卡生成

- [x] `POST /api/memory-cards` 使用客户端 `Idempotency-Key`，提交后立即返回 Card ID 和 Job ID。
- [x] Redis Streams + Consumer Group 只传输持久化 Job ID，支持 ACK 与陈旧 pending 消息接管。
- [x] 已实现 `queued`、`generating_text`、`generating_image`、`completed`、`failed` 五态状态机。
- [x] 已实现归属隔离的卡片详情和用户主动失败重试；重试通过 `parent_job_id` 创建子任务。
- [x] Provider 失败不自动重试；派发补偿仅重新发布旧的 `queued` 任务。
- [x] 同步生成接口仅在非 production 且 `ENABLE_SYNC_GENERATION=true` 时注册。
- [x] 业务错误码集中到字符串枚举，Provider 原始异常、payload 和推理内容不向客户端泄露。
- [x] Webman consumer、compensation、HTTP、monitor 四类进程已真实启动，退出计数均为 0。
- [x] 真实扣子异步冒烟完成：1 条 Job、1 次 attempt，最终状态 `completed`，临时用户已清理。
- [x] 当前完整测试基线：86 tests，343 assertions。

### 应用自有图片存储

- [x] 新增并执行迁移 `0006_create_memory_card_images.sql`，一张卡只保留一套图片元数据。
- [x] 原始扣子短链必须精确匹配 `s.coze.cn`；受信首跳后的数字分片签名 CDN 才允许继续，并对每一跳执行 HTTPS、443、公网 DNS、DNS 固定、跳转、超时和大小校验。
- [x] 支持 JPEG、PNG、WebP 原图，限制 256–8192 像素、4000 万总像素和 10 MiB；保留原图并生成最长边不超过 1024 与 512 的 WebP。
- [x] 图片原图、两份变体和 SHA-256 元数据保存到应用自有存储；数据库与 API 不保存扣子临时 URL。
- [x] AI 文本先持久化，图片成功后 Job 才进入 `completed`；稳定图片失败保留文本并由用户手动完整重试。
- [x] 提供归属隔离的单卡/用户图片清理 Business，阶段 5 删除卡片与阶段 9 删除账户时调用。
- [x] 真实扣子冒烟：Job `709` 完成，应用 URL 使用 `http://e.test/storage`；WebP 为 1024×1024 和 512×512，HTTP 均为 200，原图 SHA-256 一致，数据库扣子 URL 数为 0。
- [x] 冒烟清理后临时用户、卡片、Job、图片元数据和物理文件均为 0；四类 Webman Worker 退出计数均为 0。
- [x] 当前完整测试基线：116 tests，472 assertions。

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

- [x] 新建记忆卡时立即返回 Card ID 与 Job ID。
- [x] 使用 Redis Streams 和 `app/processes/` 消费者调用扣子。
- [x] 持久化 `queued`、`generating_text`、`generating_image`、`completed`、`failed` 状态。
- [x] 增加请求幂等键、失败手动重试与结果字段校验。
- [x] 暂时保留同步接口作为仅开发可用开关，移动端迁移完成后删除。
- [ ] 用户级 AI 限流统一放入阶段 9 的安全与部署工作，避免与队列可靠性耦合。

### 阶段 4：应用自有图片存储

- [x] 及时下载扣子临时图片，限制 HTTPS 主机、跳转、超时、MIME、尺寸和文件大小。
- [x] 生成 1024px 与 512px 移动端版本并计算校验和。
- [x] 数据库只保存应用自有 URL；提供删除卡片或账户时调用的图片清理 Business。
- [x] 先实现本地存储合同，后续可替换对象存储。

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
curl -X POST http://e.test/api/memory-cards \
  -H "Content-Type: application/json" \
  -d '{"text":"ambition"}'
```

最后一个未认证请求应返回 HTTP 401，错误码为 `UNAUTHENTICATED`。同步生成路由默认不注册。

## 7. 每次开发的固定流程

1. 阅读本文件和 `AGENTS.md`。
2. 从第一个未完成阶段继续，不跳过依赖阶段。
3. 行为变更先写失败测试，再写最小实现。
4. SQL 结构变更必须新增迁移，不修改旧迁移。
5. 执行针对性测试、完整测试、语法检查和 `git diff --check`。
6. 更新本文件中的完成状态和最新测试基线。
7. 提交并推送代码，确保另一台设备能够恢复。

## 8. 当前交接点

阶段 4 已合并到本地 `master`，迁移 `0006` 已应用，完整基线为 116 tests、472 assertions。真实扣子图片导入、Nginx 静态访问、校验和、尺寸和清理均已验证；当前没有阶段 4 功能分支。

下一次开发从“阶段 5：卡片库与跨设备同步”开始：先设计卡片列表/编辑/收藏/标签/软删除的数据合同与迁移，并在删除卡片的 Business 中调用 `DeleteStoredMemoryCardImagesBusiness`。账户级隐私删除接线保留在阶段 9，用户级 AI 限流也继续在阶段 9 统一实现。
