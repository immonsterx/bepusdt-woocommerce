# BEpusdt for WooCommerce

插件当前版本：`1.4.2`

BEpusdt for WooCommerce 是一个 WordPress + WooCommerce 加密货币收款插件，用于把 WooCommerce 订单接入 BEpusdt 后端收银台。插件负责在 WooCommerce 中注册支付网关、创建 BEpusdt 支付交易、展示前端支付入口，并通过 BEpusdt 回调或 WordPress Cron 轮询同步订单支付状态。

## BEpusdt 是什么

BEpusdt 是一个开源的个人加密货币收款网关：

```text
https://github.com/v03413/BEpusdt
```

BEpusdt 后端支持多条区块链网络和多种代币收款。实际可用的网络、币种和钱包地址以你部署的 BEpusdt 后端配置为准。本插件不会替代 BEpusdt 后端，只负责把 WordPress + WooCommerce 商城订单连接到 BEpusdt 收银台。

## 主要功能

- 在 WooCommerce 后台注册 `BEpusdt Crypto` 支付网关。
- 结账页保持 WooCommerce 默认流程，只显示一个轻量的 USDT 支付入口。
- 结账页可选显示自定义支付教程 HTML，例如 USDT 支付教程链接。
- 用户提交订单后进入 WooCommerce Thank You 页面。
- Thank You 页面显示订单信息和后台启用的加密货币支付按钮。
- 用户点击某个链或代币按钮后，插件向 BEpusdt 创建支付交易。
- 创建成功后跳转到 BEpusdt 收银台完成付款。
- 支持 BEpusdt 回调和 WordPress Cron 轮询同步 WooCommerce 订单状态。

## 目录结构

```text
bepusdt-woocommerce/
  assets/
    css/
      frontend.css
    images/
      usdt.svg
    js/
      frontend.js
  includes/
    class-bepusdt-api.php
    class-bepusdt-i18n.php
    class-bepusdt-woocommerce.php
    class-wc-gateway-bepusdt.php
  languages/
    bepusdt-woocommerce.pot
  templates/
    payment-instructions.php
  bepusdt.php
  README.md
```

## 安装方法

1. 确认 WordPress 和 WooCommerce 已安装并启用。
2. 部署 BEpusdt 后端，并确认 BEpusdt API 和收银台可以访问。
3. 将插件上传到：

```text
wp-content/plugins/
```

4. 在 WordPress 后台启用插件。
5. 进入 WooCommerce 支付设置：

```text
WooCommerce > Settings > Payments > BEpusdt Crypto
```

## 后台设置

- Enable/Disable：启用或停用支付网关。
- Title：前台支付方式标题，留空则前台不显示。
- Description：前台支付方式描述，留空则前台不显示。
- BEpusdt API URL：BEpusdt 后端地址，例如 `https://pay.example.com`。
- API Token / Secret：BEpusdt API 密钥，保存后后台会用星号隐藏。
- Frontend Payment Buttons：Thank You 页面显示哪些 BEpusdt 交易类型按钮。
- Payment Expiration：支付过期时间，单位为秒。
- Payment Guide HTML：用于设置结账页 USDT 支付教程提示，留空则结账页不显示提示。
- Automatic Status Polling：定时查询待付款订单状态，防止回调失败导致订单不同步。
- Debug Log：记录 WooCommerce 调试日志，敏感字段会脱敏。

支付货币不单独设置，插件会读取 WooCommerce 当前货币并传给 BEpusdt。

## 使用流程

1. 在 BEpusdt 后端完成收款钱包、网络、代币、汇率和收银台配置。
2. 在 WooCommerce 后台启用 `BEpusdt Crypto` 支付方式。
3. 填写 BEpusdt API URL、API Token 和 Secret。
4. 在 `Frontend Payment Buttons` 中选择前台允许用户点击的 BEpusdt 交易类型按钮。
5. 如需显示 USDT 支付教程链接，在 `Payment Guide HTML` 中填写 HTML，例如：

```html
<a href="https://yourdomain.com" target="_blank" rel="noopener noreferrer">USDT 支付教程</a>
```

6. 用户在结账页选择加密货币支付方式并提交订单。
7. 用户进入 WooCommerce Thank You 页面。
8. 用户点击需要使用的链或代币按钮。
9. 插件创建 BEpusdt 支付交易，并打开 BEpusdt 收银台。
10. 用户完成付款后，BEpusdt 通过回调或轮询结果让 WooCommerce 订单变为已支付。

如果你的 BEpusdt 后端需要手动填写回调地址，可以使用：

```text
https://your-site.example/wc-api/bepusdt_wc_notify
```

请把 `your-site.example` 替换成你的 WordPress 站点域名。

## 前端显示逻辑

结账页 USDT支付选项 默认选中，用户点击后仍保持选中状态。如果后台填写了 `Payment Guide HTML`，结账页会显示自定义教程内容；如果留空，则不显示提示。

Thank You 页面会显示真正用于创建 BEpusdt 交易的加密货币支付按钮。按钮来源于后台 `Frontend Payment Buttons` 设置。用户点击某个按钮后，插件会使用对应 `trade_type` 调用 BEpusdt 创建交易。

## BEpusdt API 对接

参考文档：

```text
https://github.com/v03413/BEpusdt/blob/main/docs/api/api.md
```

当前使用的核心接口：

- 创建支付订单：`POST /api/v1/order/create-transaction`
- 查询订单状态：`GET /api/v1/order/check-status/{trade_id}`

插件创建支付订单时会发送 WooCommerce 订单号、金额、WooCommerce 当前货币、回调地址和所选 `trade_type`。发送到 BEpusdt 的商户订单号格式为 `WooCommerce订单ID-时间戳`，例如 `30637-1778659530`。后半段是 Unix 时间戳，用于避免同一 WooCommerce 订单重复发起支付时产生重复商户订单号。

## 支持的 BEpusdt 交易类型

后台内置以下 BEpusdt `trade_type`：

- `usdt.trc20`、`usdc.trc20`、`tron.trx`
- `usdt.erc20`、`usdc.erc20`、`ethereum.eth`
- `usdt.polygon`、`usdc.polygon`
- `usdt.bep20`、`usdc.bep20`、`bsc.bnb`
- `usdt.aptos`、`usdc.aptos`
- `usdt.solana`、`usdc.solana`
- `usdt.xlayer`、`usdc.xlayer`
- `usdt.arbitrum`、`usdc.arbitrum`
- `usdc.base`
- `usdt.plasma`

如果 BEpusdt 后续新增交易类型，需要在 `includes/class-wc-gateway-bepusdt.php` 的 `trade_type_options()` 中补充对应值。

## 多语言

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

## 短代码

插件提供短代码：

```text
[bepusdt_payment_button order_id="123"]
```

用于在指定页面输出某个 WooCommerce 订单的支付按钮。

## 维护注意事项

- 每次修改代码后需要递增插件版本号。
- 版本号位置：
  - `bepusdt.php` 文件头部 `Version`
  - `bepusdt.php` 中的 `BEPUSDT_WC_VERSION`
  - `languages/bepusdt-woocommerce.pot` 中的 `Project-Id-Version`
- 前端资源使用 `BEPUSDT_WC_VERSION` 作为缓存版本号，修改 CSS/JS 后必须更新版本。
- 不建议直接修改 WooCommerce 模板文件，优先使用插件模板和 WooCommerce hooks。
- 不建议引入大型前端库，当前前端只使用原生 CSS 和 JS。
- API Token 不要输出到前端，不要写入日志明文。
- 调试日志需要继续保持敏感字段脱敏。
