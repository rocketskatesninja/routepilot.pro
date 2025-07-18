<?php

namespace App\Exceptions;

use Exception;

class ReportException extends Exception
{
    /**
     * Create a new report exception instance.
     */
    public function __construct(string $message = 'Report operation failed', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for report not found.
     */
    public static function notFound(int $id): self
    {
        return new self("Report with ID {$id} not found.", 404);
    }

    /**
     * Create an exception for report already exists.
     */
    public static function alreadyExists(string $reportNumber): self
    {
        return new self("Report with number {$reportNumber} already exists.", 409);
    }

    /**
     * Create an exception for invalid report data.
     */
    public static function invalidData(string $field): self
    {
        return new self("Invalid report data: {$field}.", 422);
    }

    /**
     * Create an exception for report deletion failed.
     */
    public static function deletionFailed(int $id): self
    {
        return new self("Failed to delete report with ID {$id}.", 500);
    }

    /**
     * Create an exception for report update failed.
     */
    public static function updateFailed(int $id): self
    {
        return new self("Failed to update report with ID {$id}.", 500);
    }

    /**
     * Create an exception for report creation failed.
     */
    public static function creationFailed(): self
    {
        return new self("Failed to create report.", 500);
    }

    /**
     * Create an exception for report generation failed.
     */
    public static function generationFailed(): self
    {
        return new self("Failed to generate report.", 500);
    }

    /**
     * Create an exception for report export failed.
     */
    public static function exportFailed(): self
    {
        return new self("Failed to export report data.", 500);
    }

    /**
     * Create an exception for report submission failed.
     */
    public static function submissionFailed(int $id): self
    {
        return new self("Failed to submit report with ID {$id}.", 500);
    }
} 