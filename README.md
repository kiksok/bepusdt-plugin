# BEpusdt Plugin For XBoard

[中文说明](./README.zh-CN.md)

This plugin connects [cedar2025/Xboard](https://github.com/cedar2025/Xboard) to [v03413/BEpusdt](https://github.com/v03413/BEpusdt) without changing XBoard core files.

## What it supports

- `cashier` mode: calls `POST /api/v1/order/create-order` and lets the user choose token/network in the BEpusdt cashier page.
- `transaction` mode: calls `POST /api/v1/order/create-transaction` and creates a fixed payment route such as `usdt.trc20`.
- MD5 signature generation and callback verification with the BEpusdt API token.
- Graceful handling of BEpusdt callback statuses:
  - `status=1`: waiting, return HTTP 200 and do not mark the XBoard order as paid.
  - `status=2`: paid, mark the XBoard order as paid.
  - `status=3`: expired, auto-cancel the pending XBoard order and return HTTP 200.

## Install

This repository is the plugin root itself.

You can install it in either way:

1. Copy this whole directory into your XBoard project as `plugins/Bepusdt`.
2. Or zip the repository root and upload it from the XBoard plugin manager.
3. In XBoard admin, install and enable the `BEpusdt` plugin.
4. Go to payment management and add a new payment method using `BEpusdt`.
5. Fill the plugin config fields described below.

## Release Package

- Direct upload package: `release/bepusdt-plugin-xboard.zip`
- Packaging script: `scripts/package.ps1`
- Checksum file: `release/SHA256SUMS.txt`

The release zip contains a top-level `Bepusdt/` directory, which is suitable for the XBoard plugin upload flow.

## Required config

- `gateway_url`: your BEpusdt domain, for example `https://pay.example.com`
- `api_token`: the API token from `System Settings -> API Settings` in BEpusdt

## Optional config

- `mode`
  - `cashier`: recommended, user chooses the available token/network on the BEpusdt page
  - `transaction`: fixed route, requires `trade_type`
- `fiat`: default `CNY`
- `product_name`: optional cashier title
- `timeout`: BEpusdt timeout in seconds
- `currencies`: only for `cashier` mode, example `USDT,USDC` or `-ETH,-BNB`
- `trade_type`: only for `transaction` mode, example `usdt.trc20`
- `address`: optional fixed receive address in `transaction` mode
- `rate`: optional BEpusdt custom rate rule in `transaction` mode

## Recommended setup

For most XBoard sites, use:

- `mode=cashier`
- `fiat=CNY`
- `currencies=USDT,USDC`

This gives one XBoard payment entry while BEpusdt handles the token/network selection page.

## Callback notes

XBoard will expose a notify URL like:

`/api/v1/guest/payment/notify/BEpusdt/{uuid}`

The plugin uses that callback to verify the BEpusdt signature and keep the XBoard order status in sync.

## Server checklist

- Your BEpusdt server must be reachable from the public internet.
- Your XBoard callback URL must be reachable by the BEpusdt server.
- Keep the XBoard and BEpusdt clocks in sync.
- Use HTTPS on both sides.

## Useful BEpusdt values

Common `trade_type` examples:

- `usdt.trc20`
- `usdt.erc20`
- `usdt.bep20`
- `usdc.polygon`
- `tron.trx`
