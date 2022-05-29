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
    public function index(): View|Factory|Application
    {
        return view('log');
    }

    /**
     * Show data log view.
     *
     * @param Request $request
     * @return Application|Factory|View
     */
    public function readFileIUpload(Request $request): View|Factory|Application
    {
        $file = $request->file;
        $dataLogFile = file($file);

        $objCountLogs = $this->logService->countLogs($file, $dataLogFile);
        $objFileInfo = $this->logService->getFileInfo($file);

        return view('log', [
            'data' => $objCountLogs,
            'fileInfo' => $objFileInfo
        ]);
    }
}
