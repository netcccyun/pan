# 彩虹外链网盘

彩虹外链网盘，是一款PHP网盘与外链分享程序，支持所有格式文件的上传，可以生成文件外链、图片外链、音乐视频外链，生成外链同时自动生成相应的UBB代码和HTML代码，还可支持文本、图片、音乐、视频在线预览，这不仅仅是一个网盘，更是一个图床亦或是音乐在线试听网站。新版本支持对接阿里云OSS、腾讯云COS、华为云OBS、又拍云、七牛云、S3协议（Amazon S3、MinIO、Cloudflare R2等）、Azure Blob 存储、OneDrive（国际版、世纪互联版）、Google Drive、WebDAV、FTP以及SFTP存储，同时增加了图片违规检测功能。

### 更新日志

[CHANGELOG](./CHANGELOG.md)


### 演示地址
- https://cccimg.com/

### 部署方法

- 环境要求`PHP` >= 8.1、`MySQL` >= 5.5，PHP 需要 `curl`、`pdo_mysql`、`gd` 扩展
- 使用 FTP 存储需要 PHP 的 `ftp` 扩展，使用 SFTP 存储需要 PHP 的 `ssh2` 扩展

1. 上传源码到网站根目录
2. 如果是下载的Source code包，还需Composer安装依赖（Release页面下载的安装包不需要）

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. 访问网站，按照提示安装
4. 后台默认账号密码：admin/123456，安装后请及时修改

- 建议禁止访问 `vendor` 、`file`目录。Apache 已在根目录的 `.htaccess` 中配置；Nginx 需要在站点配置中加上：

  ```nginx
  location ~ ^/(vendor|file|includes)/ {
      deny all;
  }
  ```

### 前端编译

前台和后台页面都是 Nuxt 4 + Nuxt UI 编写的纯静态单页应用（hash 路由），源码在 `frontend` 目录，编译后的文件已包含在程序中，只修改 PHP 代码时无需编译。

| 目录 | 说明 | 编译输出 |
|---|---|---|
| `frontend/` | 前台 | 网站根目录 `index.php`、`assets/_nuxt/` |
| `frontend/admin/` | 后台管理 | `admin/index.php`、`admin/assets/_nuxt/` |
| `frontend/layers/shared/` | 前后台共用的组件、工具函数、样式和配置 | - |

环境要求：Node.js >= 20.19、pnpm >= 10

```bash
cd frontend
pnpm install

# 编译前台和后台，并自动部署到网站根目录和 admin 目录
pnpm build

# 只编译前台 / 只编译后台
pnpm build:site
pnpm build:admin
```

### 官方网站

- https://pan.cccyun.cc/
- https://blog.cccyun.cn/
