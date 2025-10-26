<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProcessController extends Controller
{
    public function index() { return ['ok' => true, 'list' => []]; }
    public function store(Request $r) { return response()->json(['created' => true, 'got' => $r->all()], 201); }
    public function show($id) { return ['ok' => true, 'id' => (int)$id]; }
    public function update(Request $r, $id) { return ['updated' => true, 'id' => (int)$id]; }
    public function destroy($id) { return response()->noContent(); }

    // extra para insights de prueba
    public function insights($id) { return ['insights' => true, 'id' => (int)$id]; }
}
