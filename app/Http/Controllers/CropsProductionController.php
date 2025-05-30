<?php

namespace App\Http\Controllers;

use App\Http\Resources\CropsProductionResource;
use App\Imports\CropsImport;
use App\Models\CropsProduction;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Illuminate\Http\Request;

class CropsProductionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = CropsProduction::query();

        // Filtering by crop name
        if ($request->has('vegetable')) {
            $query->where('vegetable', 'like', '%' . $request->input('vegetable') . '%');
        }

        // Filtering by year
        if ($request->has('year')) {
            $query->where('year', $request->input('year'));
        }

        // Filtering by province
        if ($request->has('province')) {
            $query->where('province', 'like', '%' . $request->input('province') . '%');
        }

        // Filtering by minimum production
        if ($request->has('production_min')) {
            $query->where('production', '>=', $request->input('production_min'));
        }

        // Filtering by maximum production
        if ($request->has('production_max')) {
            $query->where('production', '<=', $request->input('production_max'));
        }

        // Search across multiple fields
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%$search%")
                    ->orWhere('year', 'like', "%$search%")
                    ->orWhere('vegetable', 'like', "%$search%")
                    ->orWhere('province', 'like', "%$search%")
                    ->orWhere('production', 'like', "%$search%")
                    ->orWhere('planted_area', 'like', "%$search%")
                    ->orWhere('harvested_area', 'like', "%$search%")
                    ->orWhere('fertilizer_type', 'like', "%$search%")
                    ->orWhere('fertilizer_amount', 'like', "%$search%");
            });
        }

        // Pagination
        $pageLength = $request->input('pageLength', 10);
        $crops = $query->paginate($pageLength);

        return $this->formatResponse($crops);
    }


    public function getDataForStats(Request $request)
    {
        $query = CropsProduction::query();

        // Filtering by crop name
        if ($request->has('vegetable')) {
            $query->where('vegetable', 'like', '%' . $request->input('vegetable') . '%');
        }

        // Filtering by year
        if ($request->has('year')) {
            $query->where('year', $request->input('year'));
        }

        // Filtering by province
        if ($request->has('province')) {
            $query->where('province', 'like', '%' . $request->input('province') . '%');
        }

        // Filtering by minimum production
        if ($request->has('production_min')) {
            $query->where('production', '>=', $request->input('production_min'));
        }

        // Filtering by maximum production
        if ($request->has('production_max')) {
            $query->where('production', '<=', $request->input('production_max'));
        }

        // Search across multiple fields
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%$search%")
                    ->orWhere('year', 'like', "%$search%")
                    ->orWhere('vegetable', 'like', "%$search%")
                    ->orWhere('province', 'like', "%$search%")
                    ->orWhere('production', 'like', "%$search%")
                    ->orWhere('planted_area', 'like', "%$search%")
                    ->orWhere('harvested_area', 'like', "%$search%")
                    ->orWhere('fertilizer_type', 'like', "%$search%")
                    ->orWhere('fertilizer_amount', 'like', "%$search%");
            });
        }

        // Pagination
        $pageLength = $request->input('pageLength', 10);
        $crops = $query->paginate($pageLength);

        return $this->formatResponse($crops);
    }

    private function formatResponse($crops)
    {
        if ($crops->isEmpty()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'No crops productions found',
                'data' => [],
                'pagination' => [
                    'current_page' => $crops->currentPage(),
                    'last_page' => $crops->lastPage(),
                    'per_page' => $crops->perPage(),
                    'total' => $crops->total(),
                ]
            ], 200);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Lists of Crops Production have been retrieved successfully',
            'data' => CropsProductionResource::collection($crops),
            'pagination' => [
                'current_page' => $crops->currentPage(),
                'last_page' => $crops->lastPage(),
                'per_page' => $crops->perPage(),
                'total' => $crops->total(),
            ]
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    // public function create()
    // {
    //     //
    // }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'digits:4', 'min:2000', "max:" . (date('Y') + 1)],
            'province' => ['required', 'string'],
            'vegetable' => ['required', 'string'],
            'production' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'planted_area' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'harvested_area' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'fertilizer_type' => ['required', 'string'],
            'fertilizer_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);

        $crops = CropsProduction::create([
            'year' => $validated['year'],
            'province' => $validated['province'],
            'vegetable' => $validated['vegetable'],
            'production' => $validated['production'],
            'planted_area' => $validated['planted_area'],
            'harvested_area' => $validated['harvested_area'],
            'fertilizer_type' => $validated['fertilizer_type'],
            'fertilizer_amount' => $validated['fertilizer_amount'],
        ]);

        return response()->json([
            'message' => 'Crops production data has been created successfully',
            'data' => new CropsProductionResource($crops)
        ], 201);

    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'mimes:xlsx']
        ]);

        $file = $request->file('file');
        $reader = ReaderEntityFactory::createXLSXReader();
        $reader->open($file->getPathname());

        $header = ['year', 'province', 'vegetable', 'production', 'planted_area', 'harvested_area', 'fertilizer_type', 'fertilizer_amount'];
        $isFirstRow = true;

        set_time_limit(0);

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->toArray();

                if ($isFirstRow) {
                    $header = $cells;
                    $isFirstRow = false;
                    continue;
                }

                $data = array_combine($header, $cells);

                CropsProduction::create([
                    'year' => $data['year'],
                    'province' => $data['province'],
                    'vegetable' => $data['vegetable'],
                    'production' => $data['production'],
                    'planted_area' => $data['planted_area'],
                    'harvested_area' => $data['harvested_area'],
                    'fertilizer_type' => $data['fertilizer_type'],
                    'fertilizer_amount' => $data['fertilizer_amount'],
                ]);
            }
        }

        $reader->close();

        return response()->json([
            'status' => 'success',
            'message' => 'Crops production data has been imported successfully'
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $crops = CropsProduction::find($id);

        if (!$crops) {
            return response()->json([
                'message' => 'Crops production data not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Crops production data has been retrieved successfully',
            'data' => new CropsProductionResource($crops)
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    // public function edit(string $id)
    // {

    // }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $crops = CropsProduction::find($id);

        if (!$crops) {
            return response()->json([
                'message' => 'Crops production data not found'
            ], 404);
        }

        $validated = $request->validate([
            'year' => ['required', 'integer', 'digits:4', 'min:2000', "max:" . (date('Y') + 1)],
            'province' => ['required', 'string'],
            'vegetable' => ['required', 'string'],
            'production' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'planted_area' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'harvested_area' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'fertilizer_type' => ['required', 'string'],
            'fertilizer_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
        ]);

        $crops->update([
            'year' => $validated['year'],
            'province' => $validated['province'],
            'vegetable' => $validated['vegetable'],
            'production' => $validated['production'],
            'planted_area' => $validated['planted_area'],
            'harvested_area' => $validated['harvested_area'],
            'fertilizer_type' => $validated['fertilizer_type'],
            'fertilizer_amount' => $validated['fertilizer_amount'],
        ]);

        return response()->json([
            'message' => 'Crops production data has been updated successfully',
            'data' => new CropsProductionResource($crops)
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $crops = CropsProduction::find($id);

        if (!$crops) {
            return response()->json([
                'message' => 'Crops production data not found'
            ], 404);
        }

        $crops->delete();

        return response()->json([
            'message' => 'Crops production data has been deleted successfully'
        ], 200);
    }
}