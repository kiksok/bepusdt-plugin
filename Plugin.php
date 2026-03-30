<?php

namespace Plugin\Bepusdt;

use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\Plugin\AbstractPlugin;
use Illuminate\Http\Request;

class Plugin extends AbstractPlugin implements PaymentInterface
{
    private const METHOD_NAME = 'BEpusdt';
    private const CASHIER_MODE = 'cashier';
    private const TRANSACTION_MODE = 'transaction';

    public function boot(): void
    {
        $this->filter('available_payment_methods', function ($methods) {
            if ($this->getConfig('enabled', true)) {
                $methods[self::METHOD_NAME] = [
                    'name' => 'BEpusdt',
                    'icon' => 'USDT',
                    'plugin_code' => $this->getPluginCode(),
                    'type' => 'plugin',
                ];
            }

            return $methods;
        });

        $this->listen('payment.notify.before', function ($payload) {
            [$method, $uuid, $request] = $payload;

            if ($method !== self::METHOD_NAME || !($request instanceof Request)) {
                return;
            }

            $callback = $this->parseCallbackPayload($request);
            if (!$callback) {
                return;
            }

            $config = $this->loadPaymentConfigByUuid((string) $uuid);
            $apiToken = $config['api_token'] ?? '';
            if (!$apiToken || !$this->verifySignature($callback, $apiToken)) {
                return;
            }

            $status = (int) ($callback['status'] ?? 0);
            if ($status === 1) {
                $this->intercept(response('success', 200));
            }

            if ($status === 3) {
                $this->cancelPendingOrder((string) ($callback['order_id'] ?? ''));
                $this->intercept(response('success', 200));
            }
        });
    }

    public function form(): array
    {
        return [
            'gateway_url' => [
                'label' => '网关地址',
                'type' => 'string',
                'required' => true,
                'description' => '填写你的 BEpusdt 域名，例如：https://pay.example.com',
            ],
            'api_token' => [
                'label' => 'API 令牌',
                'type' => 'string',
                'required' => true,
                'description' => '填写 BEpusdt 后台生成的 API Token',
            ],
            'mode' => [
                'label' => '对接模式',
                'type' => 'string',
                'default' => self::CASHIER_MODE,
                'description' => '可填 cashier 或 transaction，推荐使用 cashier',
            ],
            'fiat' => [
                'label' => '法币单位',
                'type' => 'string',
                'default' => 'CNY',
                'description' => '默认法币单位，例如 CNY、USD',
            ],
            'product_name' => [
                'label' => '商品名称',
                'type' => 'string',
                'description' => '可选，显示在 BEpusdt 收银台页面的商品标题',
            ],
            'timeout' => [
                'label' => '超时时间（秒）',
                'type' => 'number',
                'description' => 'cashier 模式最少 180 秒，transaction 模式最少 120 秒',
            ],
            'currencies' => [
                'label' => '收银台币种限制',
                'type' => 'string',
                'description' => '仅 cashier 模式使用，例如：USDT,USDC 或 -ETH,-BNB',
            ],
            'trade_type' => [
                'label' => '交易类型',
                'type' => 'string',
                'default' => 'usdt.trc20',
                'description' => '仅 transaction 模式使用，例如：usdt.trc20',
            ],
            'address' => [
                'label' => '固定地址',
                'type' => 'string',
                'description' => '可选，仅 transaction 模式使用，指定固定收款地址',
            ],
            'rate' => [
                'label' => '汇率规则',
                'type' => 'string',
                'description' => '可选，自定义汇率规则，例如：~1.02 或 +0.3',
            ],
        ];
    }

    public function pay($order): array
    {
        $gatewayUrl = $this->normalizeGatewayUrl($this->getConfig('gateway_url'));
        $apiToken = trim((string) $this->getConfig('api_token'));
        if (!$gatewayUrl || !$apiToken) {
            throw new ApiException('BEpusdt gateway_url or api_token is missing');
        }

        $mode = $this->normalizeMode((string) $this->getConfig('mode', self::CASHIER_MODE));
        $amountString = number_format(((int) $order['total_amount']) / 100, 2, '.', '');
        $amount = (float) $amountString;
        $payload = [
            'order_id' => (string) $order['trade_no'],
            'amount' => $amount,
            'notify_url' => (string) $order['notify_url'],
            'redirect_url' => (string) $order['return_url'],
            'fiat' => strtoupper((string) $this->getConfig('fiat', 'CNY')),
            'name' => $this->buildProductName((string) $order['trade_no']),
        ];

        $timeout = (int) $this->getConfig('timeout', 0);
        if ($timeout > 0) {
            $payload['timeout'] = $mode === self::CASHIER_MODE
                ? max(180, $timeout)
                : max(120, $timeout);
        }

        if ($mode === self::CASHIER_MODE) {
            $endpoint = '/api/v1/order/create-order';
            $currencies = trim((string) $this->getConfig('currencies', ''));
            if ($currencies !== '') {
                $payload['currencies'] = $currencies;
            }
        } else {
            $endpoint = '/api/v1/order/create-transaction';
            $payload['trade_type'] = trim((string) $this->getConfig('trade_type', 'usdt.trc20')) ?: 'usdt.trc20';

            $address = trim((string) $this->getConfig('address', ''));
            if ($address !== '') {
                $payload['address'] = $address;
            }

            $rate = trim((string) $this->getConfig('rate', ''));
            if ($rate !== '') {
                $payload['rate'] = $rate;
            }
        }

        $signaturePayload = $payload;
        $signaturePayload['amount'] = $amountString;
        $payload['signature'] = $this->signPayload($signaturePayload, $apiToken);

        $result = $this->postJson($gatewayUrl . $endpoint, $payload);
        if ((int) ($result['status_code'] ?? 0) !== 200) {
            $message = (string) ($result['message'] ?? 'BEpusdt create order failed');
            throw new ApiException($message);
        }

        $paymentUrl = $result['data']['payment_url'] ?? null;
        if (!$paymentUrl) {
            throw new ApiException('BEpusdt payment_url is missing');
        }

        return [
            'type' => 1,
            'data' => $paymentUrl,
        ];
    }

    public function notify($params): array|bool
    {
        $payload = is_array($params) && !empty($params) ? $params : $this->parseCallbackPayload(request());
        if (!$payload) {
            return false;
        }

        if (!$this->verifySignature($payload, (string) $this->getConfig('api_token'))) {
            return false;
        }

        if ((int) ($payload['status'] ?? 0) !== 2) {
            return false;
        }

        $tradeNo = (string) ($payload['order_id'] ?? '');
        if ($tradeNo === '') {
            return false;
        }

        $callbackNo = (string) ($payload['block_transaction_id'] ?? $payload['trade_id'] ?? '');
        if ($callbackNo === '') {
            $callbackNo = $tradeNo;
        }

        return [
            'trade_no' => $tradeNo,
            'callback_no' => $callbackNo,
            'custom_result' => 'success',
        ];
    }

    private function normalizeMode(string $mode): string
    {
        return strtolower(trim($mode)) === self::TRANSACTION_MODE
            ? self::TRANSACTION_MODE
            : self::CASHIER_MODE;
    }

    private function normalizeGatewayUrl(?string $url): string
    {
        return rtrim(trim((string) $url), '/');
    }

    private function buildProductName(string $tradeNo): string
    {
        $configuredName = trim((string) $this->getConfig('product_name', ''));
        if ($configuredName !== '') {
            return $configuredName;
        }

        return sprintf('%s Order %s', (string) admin_setting('app_name', 'Xboard'), $tradeNo);
    }

    private function signPayload(array $payload, string $token): string
    {
        $filtered = [];
        foreach ($payload as $key => $value) {
            if ($key === 'signature' || $value === null || $value === '') {
                continue;
            }
            $filtered[$key] = $value;
        }

        ksort($filtered);

        $parts = [];
        foreach ($filtered as $key => $value) {
            $parts[] = sprintf('%s=%s', $key, $this->stringifyValue($value));
        }

        return md5(implode('&', $parts) . $token);
    }

    private function verifySignature(array $payload, string $token): bool
    {
        $signature = strtolower((string) ($payload['signature'] ?? ''));
        if ($signature === '' || $token === '') {
            return false;
        }

        return hash_equals($signature, $this->signPayload($payload, $token));
    }

    private function stringifyValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_float($value)) {
            $string = rtrim(rtrim(sprintf('%.12F', $value), '0'), '.');
            return $string === '' ? '0' : $string;
        }

        return (string) $value;
    }

    private function postJson(string $url, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new ApiException('Failed to encode BEpusdt request payload');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: Xboard-BEpusdt-Plugin/1.0.0',
            ],
            CURLOPT_POSTFIELDS => $body,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new ApiException('BEpusdt request failed: ' . $error);
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new ApiException('Invalid response from BEpusdt: ' . $response);
        }

        if ($httpCode >= 400) {
            $message = (string) ($decoded['message'] ?? ('HTTP ' . $httpCode));
            throw new ApiException($message);
        }

        return $decoded;
    }

    private function parseCallbackPayload(?Request $request): ?array
    {
        if (!$request) {
            return null;
        }

        $content = trim((string) $request->getContent());
        if ($content !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $data = $request->all();
        return is_array($data) && !empty($data) ? $data : null;
    }

    private function loadPaymentConfigByUuid(string $uuid): array
    {
        if ($uuid === '') {
            return [];
        }

        $payment = Payment::where('uuid', $uuid)
            ->where('payment', self::METHOD_NAME)
            ->first();

        if (!$payment) {
            return [];
        }

        $config = $payment->config;
        return is_array($config) ? $config : [];
    }

    private function cancelPendingOrder(string $tradeNo): void
    {
        if ($tradeNo === '') {
            return;
        }

        $order = Order::where('trade_no', $tradeNo)->first();
        if (!$order || $order->status !== Order::STATUS_PENDING) {
            return;
        }

        (new OrderService($order))->cancel();
    }
}
