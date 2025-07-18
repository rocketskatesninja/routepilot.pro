<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait HasExportable
{
    /**
     * Export data to CSV.
     */
    protected function exportToCsv(Collection $data, array $headers, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = $filename . '_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        $responseHeaders = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function() use ($data, $headers) {
            $file = fopen('php://output', 'w');
            
            // Write CSV headers
            fputcsv($file, $headers);

            // Write data rows
            foreach ($data as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $responseHeaders);
    }

    /**
     * Apply filters to query based on request parameters.
     */
    protected function applyFilters($query, Request $request, array $filterFields): void
    {
        foreach ($filterFields as $field => $config) {
            if ($request->filled($field)) {
                $value = $request->input($field);
                
                if (isset($config['type']) && $config['type'] === 'boolean') {
                    $value = $request->boolean($field);
                }
                
                $column = $config['column'] ?? $field;
                $operator = $config['operator'] ?? '=';
                
                $query->where($column, $operator, $value);
            }
        }
    }
} 