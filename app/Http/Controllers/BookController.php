<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Book; 
use App\Http\Resources\Book as BookResource;
use App\Http\Resources\Books as BookResourceCollection;

class BookController extends Controller
{
    public function index()
    {
        return new BookResourceCollection(Book::paginate(6));
    }

    public function top($count)
    {
        return new BookResourceCollection(
            Book::orderByDesc('views')->limit($count)->get()
        );
    }

    public function search($keyword)
    {
        return new BookResourceCollection(
            Book::where('title', 'like', '%' . $keyword . '%')
                ->orderByDesc('views')
                ->get()
        );
    }

    public function store(Request $request)
    {
        // Implementasi menyusul
    }

    public function show($id)
    {
        return new BookResource(Book::findOrFail($id));
    }

    public function slug($slug)
    {
        $book = Book::where('slug', $slug)->firstOrFail();
        return new BookResource($book);
    }

    public function update(Request $request, $id)
    {
        // Implementasi menyusul
    }

    public function cart(Request $request)
    {
        $carts = json_decode($request->carts, true);

        if (!is_array($carts)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid cart format',
            ], 400);
        }

        $bookCarts = collect($carts)->map(function ($cart) {
            $book = Book::find($cart['id']);

            if (!$book) return null;

            $quantity = (int) $cart['quantity'];
            $stock = (int) $book->stock;

            $note = 'safe';
            if ($stock < $quantity) {
                $note = $stock > 0 ? 'out of stock' : 'unsafe';
                $quantity = $stock;
            }

            return [
                'id' => $book->id,
                'title' => $book->title,
                'cover' => $book->cover,
                'price' => $book->price,
                'quantity' => $quantity,
                'note' => $note
            ];
        })->filter()->values();

        return response()->json([
            'status' => 'success',
            'message' => 'carts',
            'data' => $bookCarts,
        ], 200);
    }
}
