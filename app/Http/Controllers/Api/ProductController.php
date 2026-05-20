<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

/**
 * API لیست محصولات فروشگاه پلتفرم — برای نمایش در سایت مالک (سایت پیشخوان).
 */
class ProductController extends Controller
{
    /**
     * GET /api/products
     */
    public function index(): JsonResponse
    {
        $products = Product::where('is_active', true)
            ->with(['module:id,slug,title', 'supportPlan:id,title,duration_days'])
            ->orderBy('type')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name_fa' => $p->name_fa,
                'name_en' => $p->name_en,
                'type' => $p->type,
                'type_label' => $p->typeLabel(),
                'price_irr' => $p->price_irr,
                'description' => $p->description,
                'module' => $p->module ? ['slug' => $p->module->slug, 'title' => $p->module->title] : null,
                'support_plan' => $p->supportPlan ? [
                    'title' => $p->supportPlan->title,
                    'duration_days' => $p->supportPlan->duration_days,
                ] : null,
            ]);

        return response()->json(['products' => $products]);
    }
}
