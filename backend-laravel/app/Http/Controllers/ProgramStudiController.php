<?php

namespace App\Http\Controllers;

use App\Models\ProgramStudi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramStudiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $jenjang = $request->query('jenjang');
        if ($jenjang !== null && !in_array($jenjang, ['D3', 'S1', 'Profesi', 'S2', 'S3'], true)) {
            return response()->json(['message' => 'Jenjang tidak valid.'], 400);
        }

        $items = ProgramStudi::query()
            ->select('id', 'jenjang', 'nama', 'fakultas')
            ->when($jenjang !== null, fn ($query) => $query->where('jenjang', $jenjang))
            ->orderByRaw("CASE jenjang WHEN 'D3' THEN 1 WHEN 'S1' THEN 2 WHEN 'Profesi' THEN 3 WHEN 'S2' THEN 4 WHEN 'S3' THEN 5 END")
            ->orderBy('nama')
            ->get();

        return response()->json($items);
    }
}
