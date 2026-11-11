<?php

declare(strict_types=1);

namespace Tests\Support;

use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

/**
 * A pretend Stripe for tests: every call Cashier makes is recorded and answered
 * with just enough of a real response for it to carry on. Nothing leaves the box.
 * Install with FakeStripe::install(); FakeStripe::uninstall() puts the real one back.
 */
final class FakeStripe implements ClientInterface
{
    /** @var list<array{method: string, path: string, params: array<string, mixed>}> */
    public array $calls = [];

    /** @var array<string, array{price: string, quantity: int}> subscription items it has handed out */
    private array $items = [];

    public static function install(): self
    {
        config(['cashier.secret' => 'sk_test_fake']);
        $fake = new self;
        ApiRequestor::setHttpClient($fake);

        return $fake;
    }

    public static function uninstall(): void
    {
        ApiRequestor::setHttpClient(null);
    }

    /** The calls that changed something (not the look-ups), as "post /v1/…". */
    public function writes(): array
    {
        return array_values(array_filter($this->calls, fn (array $c): bool => $c['method'] !== 'get'));
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $method = strtolower((string) $method);
        $path = (string) parse_url((string) $absUrl, PHP_URL_PATH);
        $params = is_array($params) ? $params : [];
        $this->calls[] = ['method' => $method, 'path' => $path, 'params' => $params];

        return [(string) json_encode($this->answer($method, $path, $params)), 200, []];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function answer(string $method, string $path, array $params): array
    {
        if (preg_match('#^/v1/prices/([\w-]+)$#', $path, $m)) {
            return $this->price($m[1]);
        }
        if ($path === '/v1/subscription_items' && $method === 'post') {
            $id = 'si_'.count($this->items);
            $this->items[$id] = ['price' => (string) $params['price'], 'quantity' => (int) $params['quantity']];

            return $this->item($id);
        }
        if (preg_match('#^/v1/subscription_items/(\w+)$#', $path, $m)) {
            $this->items[$m[1]] ??= ['price' => 'price_unknown', 'quantity' => 0];
            if ($method === 'post') {
                $this->items[$m[1]]['quantity'] = (int) $params['quantity'];
            }

            return $method === 'delete' ? ['id' => $m[1], 'object' => 'subscription_item', 'deleted' => true] : $this->item($m[1]);
        }
        if (preg_match('#^/v1/subscriptions/(\w+)$#', $path, $m)) {
            return ['id' => $m[1], 'object' => 'subscription', 'status' => 'active', 'items' => ['object' => 'list', 'data' => []]];
        }
        if ($path === '/v1/customers' && $method === 'post') {
            return ['id' => 'cus_fake', 'object' => 'customer'];
        }
        if ($path === '/v1/checkout/sessions' && $method === 'post') {
            return ['id' => 'cs_fake', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.test/cs_fake'];
        }

        return ['id' => 'obj_fake', 'object' => 'unknown'];
    }

    /** @return array<string, mixed> */
    private function price(string $id): array
    {
        return ['id' => $id, 'object' => 'price', 'product' => 'prod_fake', 'recurring' => ['interval' => 'month', 'usage_type' => 'licensed']];
    }

    /** @return array<string, mixed> */
    private function item(string $id): array
    {
        return [
            'id' => $id,
            'object' => 'subscription_item',
            'quantity' => $this->items[$id]['quantity'],
            'price' => $this->price($this->items[$id]['price']),
        ];
    }

    /** Hand out an item that's already on a subscription (one the test made in the database). */
    public function knows(string $id, string $price, int $quantity): void
    {
        $this->items[$id] = ['price' => $price, 'quantity' => $quantity];
    }
}
