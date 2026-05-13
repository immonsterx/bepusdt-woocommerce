# BEpusdt for WooCommerce

BEpusdt for WooCommerce 是一个 WordPress + WooCommerce 加密货币收款插件，用于把 WooCommerce 订单接入 BEpusdt 后端收银台。插件负责在 WooCommerce 中注册支付网关、生成支付请求、展示前端支付入口，并通过 BEpusdt 回调或轮询同步订单支付状态。

插件当前版本：`1.3.0`

## BEpusdt 是什么

BEpusdt 是一个开源的个人加密货币收款网关，项目地址：

```text
https://github.com/v03413/BEpusdt
```

BEpusdt 后端不仅支持 USDT，也支持多条区块链网络和更多币种收款。官方项目说明中列出的主流网络包括 TRON、Ethereum、BSC、Polygon，并扩展支持 X-Layer、Solana、Aptos、Arbitrum-One、Base 等网络。实际可用网络、币种和收款能力以你部署的 BEpusdt 后端配置为准。

本插件的职责不是替代 BEpusdt 后端，而是把 WordPress + WooCommerce 商城订单与 BEpusdt 收银台连接起来。插件后台已内置 BEpusdt 文档列出的主流 `trade_type`，可以选择 USDT、USDC、TRX、ETH、BNB 等币种，以及 TRON、Ethereum、Polygon、BSC、Aptos、Solana、X-Layer、Arbitrum-One、Base、Plasma 等网络。

## 项目作用

这个项目适合需要在 WooCommerce 商城中接入加密货币收款的站点。它主要完成以下工作：

- 在 WooCommerce 后台注册 BEpusdt 支付网关。
- 在 WooCommerce 结账页显示加密货币支付方式。
- 可选择显示 USDT、VISA、PayPal、Mastercard 等视觉化支付选项。
- 视觉化支付选项只用于前端展示，不代表 VISA、PayPal、Mastercard 已接入真实支付。
- 用户提交订单后，在 WooCommerce Thank You 页面插入支付卡片。
- 用户在支付卡片中选择可用链后，插件向 BEpusdt 创建支付订单。
- 创建成功后跳转到 BEpusdt 收银台完成付款。
- 通过 BEpusdt 回调或 WordPress Cron 轮询同步 WooCommerce 订单状态。

## 适用场景

- WordPress + WooCommerce 商城需要接入加密货币收款。
- 商家已经部署或准备部署 BEpusdt 后端。
- 站点希望使用 WooCommerce 默认结账流程，同时在 Thank You 页面加入加密货币支付卡片。
- 当前希望优先开放 USDT 收款，后续可能扩展更多链或代币。
- 结账页需要展示常见支付方式的视觉入口，但实际引导用户使用加密货币支付。

## 插件目录结构

```text
bepusdt-woocommerce/
  assets/
    css/
      frontend.css                # 前端结账页和 Thank You 支付卡片样式
    images/
      usdt.svg                    # USDT 视觉选项图标
      visa.svg                    # VISA 视觉选项图标
      paypal.svg                  # PayPal 视觉选项图标
      mastercard.svg              # Mastercard 视觉选项图标
    js/
      frontend.js                 # 前端支付选项点击、提示、订单状态轮询
  includes/
    class-bepusdt-api.php         # BEpusdt API 请求、签名、状态查询、日志
    class-bepusdt-i18n.php        # 多语言回退文案
    class-bepusdt-woocommerce.php # 插件主流程、资源加载、回调、轮询、短代码
    class-wc-gateway-bepusdt.php  # WooCommerce 支付网关类和后台设置
  languages/
    bepusdt-woocommerce.pot       # 翻译模板
  templates/
    payment-instructions.php      # Thank You 页面支付卡片模板
  bepusdt.php                     # 插件入口文件
  README.md                       # 插件说明文档
```

## 安装方法

1. 确认 WordPress 和 WooCommerce 已安装并启用。
2. 部署 BEpusdt 后端，并确认可以访问 BEpusdt 收银台和 API。
3. 把 `bepusdt-woocommerce` 文件夹上传到：

```text
wp-content/plugins/
```

4. 在 WordPress 后台启用插件：

```text
Plugins > BEpusdt for WooCommerce > Activate
```

5. 进入 WooCommerce 支付设置启用网关：

```text
WooCommerce > Settings > Payments > BEpusdt Crypto
```

## 后台设置说明

主要设置项：

- Enable/Disable：启用或停用支付网关。
- Title：前台支付方式标题，留空则前台不显示。
- Description：前台支付方式描述，留空则前台不显示。
- BEpusdt API URL：BEpusdt 后端地址，例如 `https://pay.example.com`。
- API Token / Secret：BEpusdt API 密钥，保存后后台按令牌字符数量显示星号。
- Frontend Payment Buttons：Thank You 页面显示哪些 BEpusdt 交易类型按钮。当前内置 BEpusdt 文档列出的主流币种和网络，默认启用 USDT TRC20、USDT Polygon、USDT ERC20。
- Payment Expiration：支付过期时间，单位为秒。
- Visual Payment Options：默认关闭，可勾选 USDT、VISA、PayPal、Mastercard，并可拖动排序；后台怎么排序，前台就怎么显示。该选项仅用于视觉化展示，无实质支付功能。
- Payment Guide HTML：用于设置前台 USDT 支付教程提示 HTML，留空则 USDT 选中时不显示提示。
- Automatic Status Polling：是否定时查询待付款订单状态，防止回调失败导致订单不同步。
- Debug Log：是否记录 WooCommerce 调试日志，敏感字段会脱敏。

支付货币不单独设置，插件会读取 WooCommerce 当前货币并传给 BEpusdt。

## 使用方法

1. 在 BEpusdt 后端完成收款钱包、网络、代币、汇率和收银台配置。
2. 在 WooCommerce 后台启用 `BEpusdt Crypto` 支付方式。
3. 填写 BEpusdt API URL 和 API Token / Secret。
4. 在 `Frontend Payment Buttons` 中选择前台允许用户点击的 BEpusdt 交易类型按钮。
5. 如需在结账页展示支付方式图标，在 `Visual Payment Options` 中勾选需要显示的视觉选项，并拖动排序。
6. 如需显示 USDT 支付教程链接，在 `Payment Guide HTML` 中填写 HTML，例如 `<a href="https://yourdomain.com" target="_blank" rel="noopener noreferrer">USDT支付教程</a>`。
7. 创建一个测试商品并下单。
8. 在结账页选择加密货币支付方式并提交订单。
9. 到达 WooCommerce Thank You 页面后，点击需要使用的链或币种按钮。
10. 插件创建 BEpusdt 支付订单，并在新页面打开 BEpusdt 收银台。
11. 用户完成付款后，BEpusdt 通过回调或轮询结果让 WooCommerce 订单变为已支付。

如果你的 BEpusdt 后端需要手动填写回调地址，可以使用：

```text
https://your-site.example/wc-api/bepusdt_wc_notify
```

请把 `your-site.example` 替换成你的 WordPress 网站域名。

## 前端支付方式逻辑

结账页视觉支付方式由后台 `Visual Payment Options` 控制，默认不显示。

- USDT：作为主视觉支付方式，默认选中；点击后保持选中。如果后台填写了 `Payment Guide HTML`，则显示自定义教程内容；如果留空，则不显示提示。
- VISA / PayPal / Mastercard：只作为视觉化选项；点击后会提示当前地址无法使用对应支付方式。如果后台填写了 `Payment Guide HTML`，提示会自动切回教程内容；如果留空，提示会自动关闭。
- USDT/加密货币视觉按钮会和 VISA / PayPal / Mastercard 在同一行并列显示，后台排序会直接用于前台排序。
- 视觉选项的后台排序会用于同一类别内的前台显示顺序。

视觉支付选项的按钮结构和样式在以下文件中维护：

```text
assets/css/frontend.css
assets/js/frontend.js
```

支付图标文件在：

```text
assets/images/usdt.svg
assets/images/visa.svg
assets/images/paypal.svg
assets/images/mastercard.svg
```

## Thank You 页面支付流程

用户在 WooCommerce 结账页提交订单后，会进入 WooCommerce Thank You 页面。插件会在该页面插入支付卡片：

1. 显示 WooCommerce 订单编号、订单日期、订单总额、支付方式、交易 ID 等信息。
2. 在支付卡片底部显示后台启用的链按钮。
3. 用户点击某个 BEpusdt 交易类型，例如 USDT TRC20、USDC Polygon 或 ETH。
4. 插件调用 BEpusdt API 创建支付交易。
5. 创建成功后，在新页面打开 BEpusdt 收银台。

如果 BEpusdt 后端没有配置任意已启用交易类型的钱包地址，例如 USDT-Solana、ETH、USDC-Polygon 等，BEpusdt 会返回创建失败信息。插件会把该错误显示回 WooCommerce Thank You 感谢订单页面的支付卡片中，不会让 WordPress 产生致命错误。
链按钮使用固定宽度排列；只有一个按钮时不会铺满整行，启用多个按钮时会从左到右继续排列，移动端会自动变为单列。

支付卡片模板文件：

```text
templates/payment-instructions.php
```

## BEpusdt API 对接

参考文档：

```text
https://github.com/v03413/BEpusdt/blob/main/docs/api/api.md
```

当前使用的核心接口：

- 创建支付订单：`POST /api/v1/order/create-transaction`
- 查询订单状态：`GET /api/v1/order/check-status/{trade_id}`

插件创建支付订单时会发送 WooCommerce 订单号、金额、当前 WooCommerce 货币、回调地址和所选 `trade_type`。BEpusdt 后端根据自己的配置生成对应收银台订单。
发送到 BEpusdt 的商户订单号格式为 `WooCommerce订单ID-时间戳`，例如 `30637-1778659530`。后半段是 Unix 时间戳，用于避免同一个 WooCommerce 订单重复发起支付时产生重复商户订单号；所选链或币种会通过独立的 `trade_type` 参数发送，不再写入商户订单号。

## 订单状态同步

插件支持两种同步方式：

- 回调同步：BEpusdt 主动请求 WooCommerce 通知地址。
- 轮询同步：WordPress Cron 定时查询待付款订单状态。

建议正式环境开启 `Automatic Status Polling`。这样即使 BEpusdt 回调失败，插件仍然有机会通过定时查询同步订单状态。

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

## 支持的 BEpusdt 交易类型

当前后台已内置以下 BEpusdt `trade_type`：

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

## 维护注意事项

- 每次修改代码后需要递增插件版本号。
- 版本号位置：
  - `bepusdt.php` 文件头部 `Version`
  - `bepusdt.php` 中的 `BEPUSDT_WC_VERSION`
  - `languages/bepusdt-woocommerce.pot` 中的 `Project-Id-Version`
- 前端资源使用 `BEPUSDT_WC_VERSION` 作为缓存版本号，修改 CSS/JS 后必须更新版本，方便浏览器刷新缓存。
- 不建议直接修改 WooCommerce 模板文件，优先使用插件模板和 WooCommerce hooks。
- 不建议引入大型前端库，当前前端只使用原生 CSS 和 JS。
- API Token 不要输出到前端，不要写入日志明文。
- 调试日志需要继续保持敏感字段脱敏。
