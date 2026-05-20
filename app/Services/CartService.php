<?php
namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Session;

/**
 * سبد خرید بر پایهٔ session.
 * هر آیتم: product_id, quantity, site_id (اختیاری؛ برای ماژول/پشتیبانی)
 */
class CartService
{
    protected const SESSION_KEY = 'platform_cart';

    public function items(): array
    {
        return Session::get(self::SESSION_KEY, []);
    }

    public function add(int $productId, int $quantity = 1, ?int $siteId = null): void
    {
        $product = Product::where('is_active', true)->findOrFail($productId);
        $key = $siteId ? "{$productId}_{$siteId}" : (string) $productId;
        $cart = $this->items();
        $cart[$key] = [
            'product_id' => $product->id,
            'quantity' => $quantity,
            'site_id' => $siteId,
        ];
        Session::put(self::SESSION_KEY, $cart);
    }

    public function remove(string $key): void
    {
        $cart = $this->items();
        unset($cart[$key]);
        Session::put(self::SESSION_KEY, $cart);
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * آیتم‌ها به‌همراه مدل Product و جمع قیمت.
     */
    public function details(): array
    {
        $items = $this->items();
        $result = [];
        $total = 0;
        foreach ($items as $key => $row) {
            $product = Product::find($row['product_id']);
            if (!$product) {
                continue;
            }
            $qty = (int) ($row['quantity'] ?? 1);
            $lineTotal = $product->price_irr * $qty;
            $total += $lineTotal;
            $result[] = [
                'key' => $key,
                'product' => $product,
                'quantity' => $qty,
                'site_id' => $row['site_id'] ?? null,
                'line_total' => $lineTotal,
            ];
        }
        return [
            'items' => $result,
            'total' => $total,
        ];
    }

    public function isEmpty(): bool
    {
        return empty($this->items());
    }
}
