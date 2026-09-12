# Typecho-Plugin-Highlight

基于 [MoXiaoXi233/PS-HighLight-Plugin](https://github.com/MoXiaoXi233/PS-HighLight-Plugin) 的 Typecho 代码高亮插件

## 安装

1. 上传仓库代码到 `/usr/plugins/` 目录，命名为 `Highlight`
2. 进入 Typecho 后台，启用插件
3. 在插件设置中选择主题

## 配置

### 主题选择

可在插件设置中选择主题，对应的 CSS 会自动注入到页面 `<head>` 中，切换即时生效。

## 评论高亮

如需在评论中使用代码高亮，需要允许 `class` 和 `style` 属性：

1. 进入 **设置 → 评论**
2. 将"评论允许的 HTML 标签"修改为：
   ```html
   <pre class="" style=""><code class="" style=""><span class="" style="">
   ```

## 目录结构

```
Highlight/
├── Plugin.php           插件入口：配置面板、钩子注册、内容高亮处理
├── Engine.php           highlight.php 高亮引擎（单例）
├── assets/
│   ├── highlight.css    行号、复制按钮等前端样式
│   └── highlight.js     复制按钮与动态代码块处理
└── vendor/              highlight.php 库（Autoloader、Highlighter 等）
    ├── languages/       语言定义文件
    └── themes/          主题 CSS 文件
```

## 引用库

highlight.php

- **仓库**: https://github.com/scrivo/highlight.php
- **版本**: 9.18.1.10
- **许可**: BSD-3-Clause

## 许可证

MIT License