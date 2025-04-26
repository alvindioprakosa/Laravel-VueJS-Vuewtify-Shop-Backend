<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Resources\Category as CategoryResource;
use App\Http\Resources\Categories as CategoryResourceCollection;

class CategoryController extends Controller
{
    public function index()
    {
        return new CategoryResourceCollection(Category::paginate(6));
    }

    public function random($count)
    {
        $categories = Category::inRandomOrder()->limit($count)->get();
        return new CategoryResourceCollection($categories);
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
        $category = Category::findOrFail($id);
        return new CategoryResource($category);
    }

    public function slug($slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();
        return new CategoryResource($category);
    }

    public function update(Request $request, $id)
    {
        // Belum diimplementasikan
        return response()->json([
            'status' => 'error',
            'message' => 'Update not implemented yet',
        ], 501);
    }

    public function destroy($id)
    {
        // Belum diimplementasikan
        return response()->json([
            'status' => 'error',
            'message' => 'Delete not implemented yet',
        ], 501);
    }
}
