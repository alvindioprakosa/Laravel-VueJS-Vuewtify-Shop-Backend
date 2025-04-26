<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\{Province, City, User, Book, Order, BookOrder};
use App\Http\Resources\Provinces as ProvinceResourceCollection;
use App\Http\Resources\Cities as CityResourceCollection;
use App\Http\Resources\Order as OrderResource;

class ShopController extends Controller
{
    public function provinces()
    {
        return new ProvinceResourceCollection(Province::all());
    }

    public function cities()
    {
        return new CityResourceCollection(City::all());
    }

    public function shipping(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->jsonError('User not found');
        }

        $validated = $request->validate([
            'name' => 'required',
            'address' => 'required',
            'phone' => 'required',
            'province_id' => 'required',
            'city_id' => 'required',
        ]);

        $user->fill($validated);

        if ($user->save()) {
            return $this->jsonSuccess('Update shipping success', $user);
        }

        return $this->jsonError('Update shipping failed');
    }

    public function couriers()
    {
        $couriers = [
            ['id' => 'jne', 'text' => 'JNE'],
            ['id' => 'tiki', 'text' => 'TIKI'],
            ['id' => 'pos', 'text' => 'POS'],
        ];

        return $this->jsonSuccess('courier', $couriers);
    }

    protected function getServices(array $data)
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.rajaongkir.com/starter/cost",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => [
                "content-type: application/x-www-form-urlencoded",
                "key: " . config('services.rajaongkir.key')
            ],
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        return compact('error', 'response');
    }

    protected function validateCart(array $carts)
    {
        $safeCarts = [];
        $total = ['quantity_before' => 0, 'quantity' => 0, 'price' => 0, 'weight' => 0];

        foreach ($carts as $cart) {
            $book = Book::find($cart['id']);
            if (!$book || $book->stock <= 0) continue;

            $quantity = min($cart['quantity'], $book->stock);
            $safeCarts[] = [
                'id' => $book->id,
                'title' => $book->title,
                'cover' => $book->cover,
                'price' => $book->price,
                'weight' => $book->weight,
                'quantity' => $quantity
            ];

            $total['quantity_before'] += $cart['quantity'];
            $total['quantity'] += $quantity;
            $total['price'] += $book->price * $quantity;
            $total['weight'] += $book->weight * $quantity;
        }

        return compact('safeCarts', 'total');
    }

    protected function convertToGram($weightInKg)
    {
        return $weightInKg * 1000;
    }

    public function services(Request $request)
    {
        $request->validate([
            'courier' => 'required',
            'carts' => 'required'
        ]);

        $user = Auth::user();
        if (!$user || !$user->city_id) {
            return $this->jsonError('User or destination not set');
        }

        $carts = json_decode($request->carts, true) ?? [];
        if (empty($carts)) {
            return $this->jsonError('Invalid carts data');
        }

        $valid = $this->validateCart($carts);
        $weight = $this->convertToGram($valid['total']['weight']);

        if ($weight <= 0) {
            return $this->jsonError('Invalid weight');
        }

        $parameter = [
            "origin" => 153,
            "destination" => $user->city_id,
            "weight" => $weight,
            "courier" => $request->courier
        ];

        $response = $this->getServices($parameter);

        if ($response['error']) {
            return $this->jsonError('Courier service unavailable: ' . $response['error']);
        }

        $decoded = json_decode($response['response']);
        $costs = $decoded->rajaongkir->results[0]->costs ?? [];

        $services = array_map(function ($cost) {
            return [
                'service' => $cost->service,
                'cost' => $cost->cost[0]->value,
                'estimation' => str_replace('hari', '', trim($cost->cost[0]->etd)),
                'resume' => $cost->service . ' [ Rp. ' . number_format($cost->cost[0]->value) . ', Etd: ' . $cost->cost[0]->etd . ' day(s) ]'
            ];
        }, $costs);

        $message = count($services) ? 'Getting services success' : 'Courier services unavailable';
        $status = $valid['total']['quantity'] !== $valid['total']['quantity_before'] ? 'warning' : 'success';

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => [
                'safe_carts' => $valid['safeCarts'],
                'total' => $valid['total'],
                'services' => $services
            ]
        ]);
    }

    public function payment(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->jsonError("User not found");
        }

        $request->validate([
            'courier' => 'required',
            'service' => 'required',
            'carts' => 'required'
        ]);

        $carts = json_decode($request->carts, true) ?? [];
        if (empty($carts)) {
            return $this->jsonError('Invalid carts data');
        }

        DB::beginTransaction();
        try {
            $order = Order::create([
                'user_id' => $user->id,
                'total_bill' => 0,
                'invoice_number' => now()->format('YmdHis'),
                'courier_service' => $request->courier . '-' . $request->service,
                'status' => 'SUBMIT'
            ]);

            $totalPrice = $totalWeight = 0;

            foreach ($carts as $cart) {
                $book = Book::find($cart['id']);
                if (!$book || $book->stock < $cart['quantity']) {
                    throw new \Exception('Book unavailable or out of stock');
                }

                BookOrder::create([
                    'book_id' => $book->id,
                    'order_id' => $order->id,
                    'quantity' => $cart['quantity']
                ]);

                $book->decrement('stock', $cart['quantity']);
                $totalPrice += $book->price * $cart['quantity'];
                $totalWeight += $book->weight * $cart['quantity'];
            }

            $weight = $this->convertToGram($totalWeight);

            if ($weight <= 0) {
                throw new \Exception('Invalid weight');
            }

            $cost = $this->getServices([
                "origin" => 153,
                "destination" => $user->city_id,
                "weight" => $weight,
                "courier" => $request->courier
            ]);

            if ($cost['error']) {
                throw new \Exception('Courier service unavailable');
            }

            $services = json_decode($cost['response'])->rajaongkir->results[0]->costs ?? [];
            $serviceCost = collect($services)->firstWhere('service', $request->service)->cost[0]->value ?? 0;

            if ($serviceCost <= 0) {
                throw new \Exception('Invalid service cost');
            }

            $order->update(['total_bill' => $totalPrice + $serviceCost]);

            DB::commit();

            return $this->jsonSuccess('Transaction success', [
                'order_id' => $order->id,
                'total_bill' => $order->total_bill,
                'invoice_number' => $order->invoice_number
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->jsonError($e->getMessage());
        }
    }

    public function myOrder()
    {
        $user = Auth::user();
        if (!$user) {
            return $this->jsonError('User not found');
        }

        $orders = Order::where('user_id', $user->id)->orderByDesc('id')->get();
        return $this->jsonSuccess('My orders', OrderResource::collection($orders));
    }

    private function jsonSuccess($message, $data = null)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ]);
    }

    private function jsonError($message, $code = 200)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'data' => null
        ], $code);
    }
}
