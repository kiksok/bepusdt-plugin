# BEpusdt 支付插件 For XBoard

[English](./README.md)

这个插件用于把 [cedar2025/Xboard](https://github.com/cedar2025/Xboard) 和 [v03413/BEpusdt](https://github.com/v03413/BEpusdt) 对接起来，不需要修改 XBoard 核心代码。

## 功能说明

- 支持 `cashier` 模式
  - 调用 `POST /api/v1/order/create-order`
  - 用户会在 BEpusdt 收银台页面自己选择币种和网络
- 支持 `transaction` 模式
  - 调用 `POST /api/v1/order/create-transaction`
  - 适合固定单一收款方式，例如 `usdt.trc20`
- 支持使用 BEpusdt 的 API Token 进行 MD5 签名和回调验签
- 兼容 BEpusdt 三种回调状态
  - `status=1`：待支付，只返回 HTTP 200，不把 XBoard 订单记为已支付
  - `status=2`：支付成功，正常给 XBoard 订单入账
  - `status=3`：订单超时，自动取消 XBoard 里对应的待支付订单

## 安装方式

这个仓库根目录本身就是插件目录。

你可以任选下面任意一种方式安装：

1. 直接把整个仓库目录复制到 XBoard 的 `plugins/Bepusdt`。
2. 使用仓库里的发布包 `release/bepusdt-plugin-xboard.zip`，在 XBoard 后台插件管理里上传安装。
3. 在 XBoard 后台安装并启用 `BEpusdt` 插件。
4. 在支付方式管理里新增一个支付方式，选择 `BEpusdt`。
5. 填写下方配置项。

## 必填配置

- `gateway_url`
  - 你的 BEpusdt 域名
  - 例如 `https://pay.example.com`
- `api_token`
  - 从 BEpusdt 后台获取
  - 一般位置是“系统设置 -> API 设置”

## 可选配置

- `mode`
  - `cashier`：推荐，用户在 BEpusdt 页面选择币种和网络
  - `transaction`：固定链路收款，需要填写 `trade_type`
- `fiat`
  - 默认 `CNY`
- `product_name`
  - 收银台显示的商品标题
- `timeout`
  - 订单超时时间，单位秒
- `currencies`
  - 仅 `cashier` 模式使用
  - 例如 `USDT,USDC` 或 `-ETH,-BNB`
- `trade_type`
  - 仅 `transaction` 模式使用
  - 例如 `usdt.trc20`
- `address`
  - `transaction` 模式下可选的固定收款地址
- `rate`
  - 可选的自定义汇率规则

## 推荐配置

大多数 XBoard 站点建议这样配：

- `mode=cashier`
- `fiat=CNY`
- `currencies=USDT,USDC`

这样 XBoard 里只需要一个支付方式入口，具体的币种和链路交给 BEpusdt 收银台处理。

## 回调说明

XBoard 会生成类似下面的异步回调地址：

`/api/v1/guest/payment/notify/BEpusdt/{uuid}`

这个插件会在回调里：

- 验证 BEpusdt 签名
- 只在 `status=2` 时标记订单已支付
- 在 `status=3` 时取消 XBoard 中对应的待支付订单

## 发布包

- 直接上传包：`release/bepusdt-plugin-xboard.zip`
- 打包脚本：`scripts/package.ps1`
- 校验文件：`release/SHA256SUMS.txt`

发布包里会带一个顶层 `Bepusdt/` 目录，这样更适合 XBoard 插件上传流程。

## 服务器部署检查项

- BEpusdt 服务端必须能从公网访问
- XBoard 的回调地址必须能被 BEpusdt 访问到
- 两边服务器时间建议保持同步
- 建议全程使用 HTTPS

## 常见 trade_type

- `usdt.trc20`
- `usdt.erc20`
- `usdt.bep20`
- `usdc.polygon`
- `tron.trx`
