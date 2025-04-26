<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
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
        $books = Book::orderByDesc('views')->limit($count)->get();
        return new BookResourceCollection($books);
    }

    public function search($keyword)
    {
        $books = Book::where('title', 'like', "%{$keyword}%")
                     ->orderByDesc('views')
                     ->get();
        return new BookResourceCollection($books);
    }

    public function store(Request $request)
    {
        // Belum diimplementasikan
        return response()->json([
            'status' => 'error',
            'message' => 'Store not implemented yet',
        ], 501);
    }

    public function show($id)
    {
        $book = Book::findOrFail($id);
        return new BookResource($book);
    }

    public function slug($slug)
    {
        $book = Book::where('slug', $slug)->firstOrFail();
        return new BookResource($book);
    }

    public function update(Request $request, $id)
    {
        // Belum diimplementasikan
        return response()->json([
            'status' => 'error',
            'message' => 'Update not implemented yet',
        ], 501);
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
            if (!$book) {
                return null;
            }

            $quantity = min((int) $cart['quantity'], (int) $book->stock);
            $note = match (true) {
                $book->stock <= 0 => 'unsafe',
                $book->stock < $cart['quantity'] => 'out of stock',
                default => 'safe',
            };

            return [
                'id' => $book->id,
                'title' => $book->title,
                'cover' => $book->cover,
                'price' => $book->price,
                'quantity' => $quantity,
                'note' => $note,
            ];
        })->filter()->values();

        return response()->json([
            'status' => 'success',
            'message' => 'Carts retrieved successfully',
            'data' => $bookCarts,
        ], 200);
    }
}
