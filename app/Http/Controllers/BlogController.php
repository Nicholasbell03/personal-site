<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index()
    {
        $blogs = ['id' => 1, 'title' => 'Blog 1', 'content' => 'Content 1'];
        return response()->json($blogs);
    }

    // public function store(Request $request)
    // {
    //     $blog = Blog::create($request->all());
    //     return response()->json($blog, 201);
    // }

    // public function show($id)
    // {
    //     $blog = Blog::findOrFail($id);
    //     return response()->json($blog);
    // }

    // public function update(Request $request, $id)
    // {
    //     $blog = Blog::findOrFail($id);
    //     $blog->update($request->all());
    //     return response()->json($blog, 200);
    // }

    // public function destroy($id)
    // {
    //     Blog::destroy($id);
    //     return response()->json(null, 204);
    // }
}