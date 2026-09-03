<?php

namespace App\Http\Controllers;

use App\Support\Dashboard\DashboardData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // Rentang divalidasi lewat daftar putih, bukan diteruskan apa adanya —
        // ia ikut menentukan berapa banyak titik yang dihitung, dan nilai
        // sembarang dari query string tidak boleh sampai ke sana.
        $range = $request->string('range')->toString();
        $range = array_key_exists($range, DashboardData::RANGES) ? $range : '30d';

        $data = new DashboardData($range);

        return Inertia::render('Dashboard/Index', [
            'range' => $range,
            'isEmpty' => $data->isEmpty(),
            'stats' => $data->stats(),
            'publications' => $data->publications(),
            'inbound' => $data->inboundMessages(),
            'sections' => $data->landingSections(),
            'activity' => $data->recentActivity(),
        ]);
    }
}
