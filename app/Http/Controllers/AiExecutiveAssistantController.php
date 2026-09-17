<?php

namespace App\Http\Controllers;

use App\Services\AiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiExecutiveAssistantController extends Controller
{
    protected AiAssistantService $aiService;

    public function __construct(AiAssistantService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Get executive summary JSON data.
     */
    public function getSummary(): JsonResponse
    {
        $summary = $this->aiService->generateExecutiveSummary();
        return response()->json($summary);
    }

    /**
     * Ask a natural language query to the AI Executive Assistant.
     */
    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|max:500',
        ]);

        $result = $this->aiService->answerExecutiveQuery($request->input('query'));
        return response()->json($result);
    }
}
