# 开奖预测系统 - 部署说明

## 文件说明

- `index.html` - 主页面（三个模块合并版）
- `server.php` - PHP服务端程序
- `start_server.bat` - Windows启动脚本

## 服务器部署

### 方法1: PHP守护进程模式（推荐）

```bash
# 进入目录
cd /path/to/game-ui

# 直接运行（每60秒更新一次）
php server.php

# 守护进程模式（持续运行）
php server.php --daemon 60
```

### 方法2: 使用cron定时任务

```bash
crontab -e

# 添加定时任务，每分钟执行一次
* * * * * /usr/bin/php /path/to/game-ui/server.php
```

### 方法3: 使用web服务器

将 `index.html` 和 `server.php` 放到web服务器目录，访问 `index.html` 即可。

PHP内置服务器测试：
```bash
php -S localhost:8080
```

## 自动刷新说明

- **页面自动刷新**: `index.html` 每30秒自动刷新一次
- **服务端更新**: `server.php` 默认每60秒从API获取并更新数据
- **服务器状态**: 页面左下角显示服务端连接状态

## 数据文件

运行后会在同目录生成：
- `lottery_data.json` - 最新开奖和预测数据
- `lottery.log` - 运行日志

## 依赖

- PHP 5.6+
- curl扩展（用于获取远程API数据）
