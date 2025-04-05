<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
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
        return new CategoryResourceCollection(
            Category::inRandomOrder()->limit($count)->get()
        );
    }

    public function store(Request $request)
    {
        // Implementasi create category (optional)
    }

    public function show($id)
    {
        return new CategoryResource(Category::findOrFail($id));
    }

    public function slug($slug)
    {
        return new CategoryResource(Category::where('slug', $slug)->firstOrFail());
    }

    public function update(Request $request, $id)
    {
        // Implementasi update category (optional)
    }

    public function destroy($id)
    {
        // Implementasi delete category (optional)
    }
}
