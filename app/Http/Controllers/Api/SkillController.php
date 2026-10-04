<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    public function index()
    {
        $skills = Skill::all();

        return response()->json($skills);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:skills,name',
        ]);

        $skill = Skill::create($validated);

        return response()->json([
            'message' => 'Skill created successfully',
            'skill' => $skill,
        ], 201);
    }

    public function show(string $id)
    {
        $skill = Skill::find($id);

        if (!$skill) {
            return response()->json([
                'message' => 'Skill not found'
            ], 404);
        }

        return response()->json($skill);
    }

    public function update(Request $request, string $id)
    {
        $skill = Skill::find($id);

        if (!$skill) {
            return response()->json([
                'message' => 'Skill not found'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:skills,name,' . $id,
        ]);

        $skill->update($validated);

        return response()->json([
            'message' => 'Skill updated successfully',
            'skill' => $skill,
        ]);
    }

    public function destroy(string $id)
    {
        $skill = Skill::find($id);

        if (!$skill) {
            return response()->json([
                'message' => 'Skill not found'
            ], 404);
        }

        $skill->delete();

        return response()->json([
            'message' => 'Skill deleted successfully'
        ]);
    }
}