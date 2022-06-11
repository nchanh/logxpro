<?php

namespace App\Http\Controllers;

use App\Http\Services\LogService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LogController extends Controller
{
    protected LogService $logService;

    public function __construct(LogService $logService)
    {
        $this->logService = $logService;
    }

    /**
     * Show log view.
     *
     * @return Application|Factory|View
     */
    public function index()
    {
        return view('log');
    }

    /**
     * Show data log view.
     *
     * @param Request $request
     * @return Application|Factory|View
     */
    public function readFileIUpload(Request $request)
    {
        $inputFile = $request->file;
        $dataLogFile = file($inputFile);

        $countLogs = $this->logService->countLogs($inputFile, $dataLogFile);
        $file = $this->logService->getInfoFile($inputFile);

        return view('log', [
            'data' => $countLogs,
            'file' => $file
        ]);
    }
}
