<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use Illuminate\Http\Request;

class PaymentGatewayController extends Controller
{
    public function index()
    {
        $gateways = PaymentGateway::orderBy('sort_order')->orderBy('id')->get();
        return view('admin.payment-gateways.index', compact('gateways'));
    }

    public function create()
    {
        return view('admin.payment-gateways.create');
    }

    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'driver' => 'required|in:' . implode(',', array_keys(PaymentGateway::drivers())),
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
        $this->addConfigValidationRules($rules, $request->driver);
        $request->validate($rules);

        $config = $this->buildConfig($request);
        $data = [
            'name' => $request->name,
            'driver' => $request->driver,
            'config' => $config,
            'is_active' => $request->boolean('is_active', true),
            'is_default' => $request->boolean('is_default', false),
        ];

        if ($data['is_default']) {
            PaymentGateway::where('is_default', true)->update(['is_default' => false]);
        }

        PaymentGateway::create($data);
        return redirect()->route('admin.payment-gateways.index')->with('success', 'درگاه با موفقیت ایجاد شد.');
    }

    public function edit(PaymentGateway $paymentGateway)
    {
        return view('admin.payment-gateways.edit', compact('paymentGateway'));
    }

    public function update(Request $request, PaymentGateway $paymentGateway)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'driver' => 'required|in:' . implode(',', array_keys(PaymentGateway::drivers())),
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
        $this->addConfigValidationRules($rules, $request->driver);
        $request->validate($rules);

        $config = $this->buildConfig($request);
        $data = [
            'name' => $request->name,
            'driver' => $request->driver,
            'config' => $config,
            'is_active' => $request->boolean('is_active'),
            'is_default' => $request->boolean('is_default'),
        ];

        if ($data['is_default']) {
            PaymentGateway::where('is_default', true)->where('id', '!=', $paymentGateway->id)->update(['is_default' => false]);
        }

        $paymentGateway->update($data);
        return redirect()->route('admin.payment-gateways.index')->with('success', 'درگاه بروزرسانی شد.');
    }

    public function destroy(PaymentGateway $paymentGateway)
    {
        if ($paymentGateway->invoices()->exists()) {
            return redirect()->route('admin.payment-gateways.index')
                ->with('error', 'این درگاه در فاکتورها استفاده شده و قابل حذف نیست.');
        }
        $paymentGateway->sites()->update(['payment_gateway_id' => null]);
        $paymentGateway->delete();

        if ($paymentGateway->is_default) {
            $first = PaymentGateway::where('is_active', true)->first();
            if ($first) {
                $first->update(['is_default' => true]);
            }
        }

        return redirect()->route('admin.payment-gateways.index')->with('success', 'درگاه حذف شد.');
    }

    private function addConfigValidationRules(array &$rules, string $driver): void
    {
        if ($driver === PaymentGateway::DRIVER_ZARINPAL) {
            $rules['merchant_id'] = 'nullable|string|max:255';
        }
        if ($driver === PaymentGateway::DRIVER_IDPAY) {
            $rules['api_key'] = 'nullable|string|max:255';
        }
        $rules['sandbox'] = 'boolean';
    }

    private function buildConfig(Request $request): array
    {
        $driver = $request->driver;
        $config = [];

        if ($driver === PaymentGateway::DRIVER_ZARINPAL) {
            $config['merchant_id'] = trim((string) $request->input('merchant_id', ''));
            $config['sandbox'] = $request->boolean('sandbox', true);
        }
        if ($driver === PaymentGateway::DRIVER_IDPAY) {
            $config['api_key'] = trim((string) $request->input('api_key', ''));
            $config['sandbox'] = $request->boolean('sandbox', true);
        }

        return $config;
    }
}
