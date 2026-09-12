# Typecho-Plugin-Highlight

基于 [MoXiaoXi233/PS-HighLight-Plugin](https://github.com/MoXiaoXi233/PS-HighLight-Plugin) 的 Typecho 代码高亮插件

## 安装

1. 上传仓库代码到 `/usr/plugins/` 目录，命名为 `Highlight`
2. 进入 Typecho 后台，启用插件
3. 在插件设置中选择引擎和主题

## 配置

### 引擎选择

| 引擎 | 优点 | 缺点 | CSS 样式 |
|------|------|------|----------|
| **highlight.php** | 速度快，兼容性好 | 精度较低 | 自动加载 ✅ |
| **Phiki** | 精度高，支持嵌套 | 相对较慢 | 内联样式 ✅ |

**说明**：
- highlight.php 引擎会自动在页面 `<head>` 中注入对应主题的 CSS
- Phiki 引擎使用内联样式，无需外部 CSS 文件
- 主题切换即时生效，无需任何手动操作

## 评论高亮

如需在评论中使用代码高亮，需要允许 `class` 和 `style` 属性：

1. 进入 **设置 → 评论**
2. 将"评论允许的 HTML 标签"修改为：
   ```html
   <pre class="" style=""><code class="" style=""><span class="" style="">
   ```

## 引用库

### highlight.php

- **仓库**: https://github.com/scrivo/highlight.php
- **版本**: 9.18.1.10
- **许可**: BSD-3-Clause

### Phiki

- **仓库**: https://github.com/tapio475/phiki
- **许可**: MIT

## 许可证

MIT License