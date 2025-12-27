<?php

namespace App\Http\Controllers;

use App\Models\Section;

class SectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $sections = Section::where('is_active', true)->get();

        return response()->json($sections);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $section = Section::where('is_active', true)->findOrFail($id);

        return response()->json($section);
    }
}
