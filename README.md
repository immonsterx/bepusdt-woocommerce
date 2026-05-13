# BEpusdt for WooCommerce

这是一个面向 WooCommerce 的 USDT 支付网关插件，用于把 WooCommerce 订单接入 BEpusdt 后端收银台。插件会在 WooCommerce 结账页提供 USDT 支付方式，并在订单提交后的 Thank You 页面显示 USDT 网络选择卡片。用户选择可用链后，插件会创建 BEpusdt 支付订单并跳转到 BEpusdt 收银台完成付款。

插件当前版本：`1.1.9`

## 项目作用

本项目的核心作用是让 WordPress + WooCommerce 商城支持 USDT 收款。

它负责完成以下工作：

- 在 WooCommerce 后台注册一个 USDT 支付网关。
- WooCommerce 后台可选择是否显示 USDT、VISA、PayPal、Mastercard 等视觉支付选项。
- 如果显示 USDT 视觉选项，USDT 默认选中，并显示 `👉 查看 USDT 支付教學` 链接。
- VISA、PayPal、Mastercard 只作为视觉选项，不可真正选择；点击时按品牌提示，例如：`當前地址無法使用 VISA 支付，請選擇 USDT 支付。`
- 订单提交成功后，在 WooCommerce Thank You 页面插入 USDT 支付卡片。
- 在支付卡片中显示订单信息和可用 USDT 网络按钮。
- 用户点击 USDT 网络后，插件向 BEpusdt 后端创建支付订单。
- 创建成功后跳转到 BEpusdt 收银台。
- 通过 BEpusdt 回调或定时轮询同步 WooCommerce 订单支付状态。

## 适用场景

这个插件适合以下场景：

- WooCommerce 商城需要接入 USDT 支付。
- 商家已经部署或准备部署 BEpusdt 后端。
- 结账页需要展示常见国际支付方式的视觉选项，但实际只开放 USDT。
- 需要支持 TRC20、Polygon、ERC20 等 USDT 链。
- 希望在 WooCommerce 默认结账和 Thank You 页面内完成支付引导，不重写整套 WooCommerce 页面。

## 插件目录结构

```text
bepusdt-woocommerce/
  assets/
    css/
      frontend.css              # 前端结账页、Thank You 支付卡片样式
    images/
      usdt.svg                  # USDT 视觉选项图标
      visa.svg                  # Visa 视觉选项图标
      paypal.svg                # PayPal 视觉选项图标
      mastercard.svg            # Mastercard 视觉选项图标
    js/
      frontend.js               # 前端支付选项点击、提示、订单状态轮询
  includes/
    class-bepusdt-api.php       # BEpusdt API 请求、签名、状态查询、日志
    class-bepusdt-i18n.php      # 多语言回退文案
    class-bepusdt-woocommerce.php # 插件主流程、资源加载、回调、轮询、短代码
    class-wc-gateway-bepusdt.php  # WooCommerce 支付网关类和后台设置
  languages/
    bepusdt-woocommerce.pot     # 翻译模板
  templates/
    payment-instructions.php    # Thank You 页面支付卡片模板
  bepusdt.php                   # 插件入口文件
  README.md                     # 项目说明和维护文档
```

## 前端支付方式逻辑

结账页的视觉支付方式在 `includes/class-wc-gateway-bepusdt.php` 的 `payment_fields()` 方法中输出。

当前输出结构：

- USDT 按钮：
  - 默认带有 `bepusdt-wc-method--active`
  - 带有 `data-bepusdt-primary-method`
  - `aria-pressed="true"`
  - 永远保持选中状态

- Visa / PayPal / Mastercard：
  - 带有 `data-bepusdt-disabled-method`
  - 带有 `aria-disabled="true"`
  - 点击后只显示提示，不会进入选中状态

每个支付选项图片外都有一层圆角边框外框：

```html
<span class="bepusdt-wc-method-card">
  <img src="..." alt="" loading="lazy" />
</span>
```

前端样式在 `assets/css/frontend.css`：

- `.bepusdt-wc-method-card` 控制每个支付图片外面的圆角边框。
- USDT 选中态直接绑定 `button[data-bepusdt-primary-method]`，即使外部脚本移除 active class，USDT 也会继续显示为选中。
- 外层 `button` 已清除主题默认按钮背景、边框和阴影，尽量避免主题样式覆盖。

前端交互在 `assets/js/frontend.js`：

- 拦截 `pointerdown` 和 `click`。
- USDT 点击后会继续保持选中并隐藏提示。
- 其他方式点击后保持 USDT 选中并显示不可用提示。
- WooCommerce 更新结账片段后，会自动恢复 USDT 选中状态。

## Thank You 页面支付流程

用户在 WooCommerce 结账页选择 USDT 支付并提交订单后：

1. WooCommerce 创建订单。
2. 插件把订单状态设为待付款。
3. 用户跳转到 WooCommerce Thank You 页面。
4. 插件通过 `woocommerce_thankyou_bepusdt` 钩子插入支付卡片。
5. 支付卡片显示订单编号、订单日期、订单总额、支付方式、交易 ID 等信息。
6. 支付卡片底部显示后台启用的 USDT 网络按钮。
7. 用户点击某条链，例如 TRC20 或 Polygon。
8. 插件调用 BEpusdt 创建支付订单。
9. 创建成功后新页面跳转到 BEpusdt 收银台。

支付卡片模板文件：

```text
templates/payment-instructions.php
```

## 后台设置项

后台路径：

```text
WooCommerce > Settings > Payments > BEpusdt USDT
```

主要设置：

- Enable/Disable：启用或停用 USDT 支付网关。
- Title：前台支付方式标题，留空则前台不显示标题。
- Description：前台支付方式说明，留空则前台不显示说明。
- BEpusdt API URL：BEpusdt 后端地址。
- API Token / Secret：BEpusdt API 密钥，保存后后台按令牌字符数量显示星号。
- Payment Currency：不提供独立设置，插件始终使用 WooCommerce 当前货币发送给 BEpusdt。
- Frontend Chain Buttons：Thank You 页面可显示的 USDT 网络按钮。
- Payment Expiration：支付过期时间，单位秒。
- Visual Payment Options：默认关闭；可多选 USDT、VISA、PayPal、Mastercard，选择什么前台就显示什么。该项仅为视觉化选项，无实质支付功能。
- Automatic Status Polling：是否定时查询待付款 USDT 订单状态。
- Debug Log：是否记录 WooCommerce 调试日志。

## BEpusdt API 对接

参考文档：

```text
https://github.com/v03413/BEpusdt/blob/main/docs/api/api.md
```

当前使用的核心接口：

- 创建支付订单：`POST /api/v1/order/create-transaction`
- 查询订单状态：`GET /api/v1/order/check-status/{trade_id}`

插件创建支付订单时会发送 WooCommerce 订单号、金额、回调地址、支付链等信息。

插件通知地址：

```text
https://your-site.example/wc-api/bepusdt_wc_notify
```

当 BEpusdt 回调或轮询结果表示支付成功时，插件会把 WooCommerce 订单标记为已支付。

## 订单状态同步方式

插件支持两种订单同步方式：

- 回调同步：BEpusdt 主动请求 WooCommerce 通知地址。
- 轮询同步：WordPress Cron 定时查询待付款订单状态。

建议正式环境保持 `Automatic Status Polling` 开启。这样即使 BEpusdt 回调失败，插件仍有机会通过定时查询同步订单状态。

## 多语言说明

插件使用 WordPress 标准翻译函数：

- `__()`
- `_e()`
- `esc_html__()`
- `esc_attr_e()`

文本域：

```text
bepusdt-woocommerce
```

如果站点启用多语言插件，前台文案会根据当前 WordPress locale 尝试显示对应语言。简体中文和繁体中文有内置回退文案。

## 样式修改入口

前端结账页视觉支付选项：

```text
assets/css/frontend.css
```

重点 class：

- `.bepusdt-wc-method-grid`：支付选项网格布局。
- `.bepusdt-wc-method`：支付按钮外层。
- `.bepusdt-wc-method-card`：支付图片外面的圆角边框卡片。
- `.bepusdt-wc-method--active`：选中状态。
- `[data-bepusdt-primary-method]`：USDT 主支付方式。
- `[data-bepusdt-disabled-method]`：不可选视觉支付方式。

如果要替换支付图标，直接替换：

```text
assets/images/usdt.svg
assets/images/visa.svg
assets/images/paypal.svg
assets/images/mastercard.svg
```

## 交互修改入口

前端点击提示和选中状态：

```text
assets/js/frontend.js
```

重要函数：

- `showNotice()`：显示不可用提示。
- `hideNotice()`：隐藏提示。
- `selectMethod()`：设置 USDT 选中状态。
- `resetCheckoutMethods()`：WooCommerce 刷新后恢复 USDT 选中。
- `keepPrimarySelected()`：点击 USDT 后强制保持选中。
- `handleMethodEvent()`：处理支付选项点击。

## 后续维护注意事项

- 每次修改代码后需要递增插件版本号。
- 版本号位置：
  - `bepusdt.php` 文件头部 `Version`
  - `bepusdt.php` 中的 `BEPUSDT_WC_VERSION`
  - `languages/bepusdt-woocommerce.pot` 中的 `Project-Id-Version`
- 前端资源使用 `BEPUSDT_WC_VERSION` 作为缓存版本号，修改 CSS/JS 后必须更新版本，方便浏览器刷新缓存。
- 不建议直接改 WooCommerce 模板文件，优先使用插件内模板和 WooCommerce hooks。
- 不建议引入大型前端库，当前前端只使用原生 CSS 和 JS。
- API Token 不要输出到前端，不要写入日志明文。
- 调试日志需要继续保持敏感字段脱敏。

## 安装方式

1. 把 `bepusdt-woocommerce` 文件夹上传到：

```text
wp-content/plugins/
```

2. 在 WordPress 后台启用插件：

```text
Plugins > BEpusdt for WooCommerce > Activate
```

3. 进入 WooCommerce 支付设置，启用并配置 USDT 支付。

## 短代码

插件提供短代码：

```text
[bepusdt_payment_button order_id="123"]
```

用于在指定页面输出某个 WooCommerce 订单的 USDT 支付按钮。
